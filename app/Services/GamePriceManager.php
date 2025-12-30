<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GamePriceManager
{
    protected $priceChartingService;
    protected $ebayService;
    protected $cexService;
    protected $amazonService;
    protected $playstationService;
    protected $steamService;
    protected $gogService;

    public function __construct(
        PriceChartingService $priceChartingService,
        EbayService $ebayService,
        CexService $cexService,
        AmazonService $amazonService,
        PlaystationService $playstationService,
        SteamService $steamService,
        GogService $gogService
    ) {
        $this->priceChartingService = $priceChartingService;
        $this->ebayService = $ebayService;
        $this->cexService = $cexService;
        $this->amazonService = $amazonService;
        $this->playstationService = $playstationService;
        $this->steamService = $steamService;
        $this->gogService = $gogService;
    }

    /**
     * Update the price for a game instance.
     *
     * @param Game $game
     * @return array|null Returns ['price' => float, 'source' => string] on success, null on failure.
     */
    public function updateGamePrice(Game $game)
    {
        Log::info("Starting price refresh for game: {$game->title} (ID: {$game->id})");

        $marketPrices = [];
        $bestPrice = null;
        $bestSource = '';

        // Helper to add price
        $addPrice = function($source, $price) use (&$marketPrices) {
            $marketPrices[] = [
                'source' => $source,
                'price' => (float) $price,
                'currency' => 'GBP', // Assuming all normalized to GBP for now
                'fetched_at' => now()->toIso8601String(),
            ];
        };

        $game->loadMissing('platform');
        $platformName = $game->platform ? $game->platform->name : null;

        // 1. PriceCharting (Best for Console/Physical Collection Value)
        if (config('services.pricecharting.key')) {
            Log::info("Checking PriceCharting for: {$game->title}");
            $pcPrice = $this->priceChartingService->getPrice($game->title, $platformName);
            if ($pcPrice !== null) {
                $addPrice('PriceCharting', $pcPrice);
                Log::info("Price found on PriceCharting: {$pcPrice}");
            }
        }

        // 2. eBay (Avg. BIN)
        if (true) { // Always check eBay if enabled
            Log::info("Checking eBay for: {$game->title}");
            $ebayPrice = $this->ebayService->getPrice($game->title, $platformName);
            if ($ebayPrice !== null) {
                $addPrice('eBay', $ebayPrice);
                Log::info("Price found on eBay: {$ebayPrice}");
            }
        }

        // 3. CeX (Best for UK Physical Games)
        if (true) {
            Log::info("Checking CeX for: {$game->title}");
            $cexPrice = $this->cexService->getPrice($game->title, $platformName);
            if ($cexPrice !== null) {
                $addPrice('CeX', $cexPrice);
                Log::info("Price found on CeX: {$cexPrice}");
            }
        }

        // 4. Amazon (Physical/New)
        if (true) {
            Log::info("Checking Amazon for: {$game->title}");
            $amazonPrice = $this->amazonService->getPrice($game->title, $platformName);
            if ($amazonPrice !== null) {
                $addPrice('Amazon', $amazonPrice);
                Log::info("Price found on Amazon: {$amazonPrice}");
            }
        }

        // 5. PlayStation Store
        if ($game->platform) {
            $platName = strtolower($game->platform->name);
            $platSlug = strtolower($game->platform->slug);
            if (str_contains($platName, 'playstation') || str_contains($platName, 'ps') || str_contains($platSlug, 'ps')) {
                Log::info("Checking PlayStation Store for: {$game->title}");
                $psPrice = $this->playstationService->getPrice($game->title);
                if ($psPrice !== null) {
                    $addPrice('PlayStation Store', $psPrice);
                    Log::info("Price found on PlayStation Store: {$psPrice}");
                }
            }
        }

        // 6. Steam
        if ($game->steam_appid) {
            Log::info("Checking Steam (AppID: {$game->steam_appid})");
            $steamPrice = $this->steamService->getPrice($game->steam_appid);
            if ($steamPrice !== null) {
                $addPrice('Steam', $steamPrice);
                Log::info("Price found on Steam: {$steamPrice}");
            }
        } elseif ($game->platform && $game->platform->slug === 'pc') {
            Log::info("Searching Steam for: {$game->title}");
            $steamData = $this->steamService->searchAndGetPrice($game->title);
            if ($steamData) {
                $addPrice('Steam', $steamData['price']);
                Log::info("Price found on Steam Search: {$steamData['price']}");
                if (!$game->steam_appid) {
                    $game->steam_appid = $steamData['appid'];
                    $game->save();
                }
            }
        }

        // 7. GOG
        Log::info("Checking GOG for: {$game->title}");
        $gogPrice = $this->gogService->getPrice($game->title);
        if ($gogPrice !== null) {
            $addPrice('GOG', $gogPrice);
            Log::info("Price found on GOG: {$gogPrice}");
        }

        // 8. CheapShark
        Log::info("Checking CheapShark for: {$game->title}");
        $cheapSharkPrice = $this->getCheapSharkPrice($game->title);
        if ($cheapSharkPrice !== null) {
            $priceGbp = $cheapSharkPrice * 0.82;
            $addPrice('CheapShark', $priceGbp);
            Log::info("Price found on CheapShark: {$priceGbp}");
        }

        // Determine Best Price (Prioritize: PriceCharting > eBay > CeX > Amazon > Store APIs)
        // We iterate through the marketPrices to find the first one that matches our priority list
        $priorityOrder = ['PriceCharting', 'eBay', 'CeX', 'Amazon', 'PlayStation Store', 'Steam', 'GOG', 'CheapShark'];
        
        foreach ($priorityOrder as $sourceName) {
            foreach ($marketPrices as $mp) {
                if (str_contains($mp['source'], $sourceName)) {
                    $bestPrice = $mp['price'];
                    $bestSource = $mp['source'];
                    break 2;
                }
            }
        }

        // Fallback if no priority match (just take the first one)
        if ($bestPrice === null && count($marketPrices) > 0) {
            $bestPrice = $marketPrices[0]['price'];
            $bestSource = $marketPrices[0]['source'];
        }

        if ($bestPrice !== null) {
            $game->update([
                'current_price' => $bestPrice,
                'price_source' => $bestSource,
                'market_prices' => $marketPrices // Save all prices
            ]);
            
            $message = "Price updated via $bestSource. Found " . count($marketPrices) . " sources.";
            Log::info("Price refresh successful: {$message}");
            
            return [
                'price' => $bestPrice,
                'source' => $bestSource,
                'market_prices' => $marketPrices,
                'message' => $message
            ];
        } else {
            Log::warning("Price refresh failed: No prices found for {$game->title}");
            return null;
        }
    }

    private function getCheapSharkPrice($title)
    {
        try {
            $response = Http::get("https://www.cheapshark.com/api/1.0/games", [
                'title' => $title,
                'limit' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data)) {
                    // CheapShark returns 'cheapest' as a string, e.g., "19.99"
                    return (float) $data[0]['cheapest'];
                }
            }
        } catch (\Exception $e) {
            // Log error or ignore
            Log::error("CheapShark API Error: " . $e->getMessage());
        }
        return null;
    }
}
