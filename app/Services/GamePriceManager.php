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
    protected $playstationService;
    protected $steamService;
    protected $gogService;

    public function __construct(
        PriceChartingService $priceChartingService,
        EbayService $ebayService,
        CexService $cexService,
        PlaystationService $playstationService,
        SteamService $steamService,
        GogService $gogService
    ) {
        $this->priceChartingService = $priceChartingService;
        $this->ebayService = $ebayService;
        $this->cexService = $cexService;
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

        $price = null;
        $source = '';

        // 1. Try PriceCharting (Best for Console/Physical Collection Value)
        if (config('services.pricecharting.key')) {
            Log::info("Checking PriceCharting for: {$game->title}");
            $game->loadMissing('platform');
            $platformName = $game->platform ? $game->platform->name : null;
            $pcPrice = $this->priceChartingService->getPrice($game->title, $platformName);
            if ($pcPrice !== null) {
                $price = $pcPrice;
                $source = 'PriceCharting (Value)';
                Log::info("Price found on PriceCharting: {$price}");
            } else {
                Log::info("No price found on PriceCharting");
            }
        } else {
            Log::info("Skipping PriceCharting: API key not configured");
        }

        // 1.5 Try eBay (if enabled and no price yet)
        if ($price === null) {
            Log::info("Checking eBay for: {$game->title}");
            $game->loadMissing('platform');
            $platformName = $game->platform ? $game->platform->name : null;
            $ebayPrice = $this->ebayService->getPrice($game->title, $platformName);
            if ($ebayPrice !== null) {
                $price = $ebayPrice;
                $source = 'eBay (Avg. BIN)';
                Log::info("Price found on eBay: {$price}");
            } else {
                Log::info("No price found on eBay");
            }
        }

        // 1.8 Try CeX (Best for UK Physical Games)
        if ($price === null) {
            Log::info("Checking CeX for: {$game->title}");
            $game->loadMissing('platform');
            $platformName = $game->platform ? $game->platform->name : null;
            $cexPrice = $this->cexService->getPrice($game->title, $platformName);
            if ($cexPrice !== null) {
                $price = $cexPrice;
                $source = 'CeX (UK)';
                Log::info("Price found on CeX: {$price}");
            } else {
                Log::info("No price found on CeX");
            }
        }

        // 2. Try PlayStation Store if platform matches
        if ($price === null && $game->platform) {
            $platName = strtolower($game->platform->name);
            $platSlug = strtolower($game->platform->slug);
            if (str_contains($platName, 'playstation') || str_contains($platName, 'ps') || str_contains($platSlug, 'ps')) {
                Log::info("Checking PlayStation Store for: {$game->title}");
                $psPrice = $this->playstationService->getPrice($game->title);
                if ($psPrice !== null) {
                    $price = $psPrice;
                    $source = 'PlayStation Store';
                    Log::info("Price found on PlayStation Store: {$price}");
                } else {
                    Log::info("No price found on PlayStation Store");
                }
            }
        }

        // 3. Try Steam Store API if AppID exists and no price yet
        if ($price === null && $game->steam_appid) {
            Log::info("Checking Steam (AppID: {$game->steam_appid})");
            $steamPrice = $this->steamService->getPrice($game->steam_appid);
            if ($steamPrice !== null) {
                $price = $steamPrice;
                $source = 'Steam';
                Log::info("Price found on Steam: {$price}");
            } else {
                Log::info("No price found on Steam (AppID)");
            }
        }

        // 3. Try Steam Search if no price yet (and PC platform)
        if ($price === null && $game->platform && $game->platform->slug === 'pc') {
            Log::info("Searching Steam for: {$game->title}");
            $steamData = $this->steamService->searchAndGetPrice($game->title);
            if ($steamData) {
                $price = $steamData['price'];
                $source = 'Steam (Search)';
                Log::info("Price found on Steam Search: {$price}");
                // Optionally save the AppID for future use
                if (!$game->steam_appid) {
                    $game->steam_appid = $steamData['appid'];
                    // We can save here or let the caller save. 
                    // Since we are inside the service method which is about "updating game price", 
                    // updating appid is a side effect but acceptable.
                    // However, we want to persist it.
                    $game->save(); 
                }
            } else {
                Log::info("No price found on Steam Search");
            }
        }

        // 4. Try GOG API
        if ($price === null) {
            Log::info("Checking GOG for: {$game->title}");
            $gogPrice = $this->gogService->getPrice($game->title);
            if ($gogPrice !== null) {
                $price = $gogPrice;
                $source = 'GOG';
                Log::info("Price found on GOG: {$price}");
            } else {
                Log::info("No price found on GOG");
            }
        }

        // 4. Try CheapShark API if no price found yet
        if ($price === null) {
            Log::info("Checking CheapShark for: {$game->title}");
            $cheapSharkPrice = $this->getCheapSharkPrice($game->title);
            if ($cheapSharkPrice !== null) {
                // CheapShark is in USD, convert to GBP (Approximate rate: 0.82)
                $price = $cheapSharkPrice * 0.82; 
                $source = 'CheapShark (Est.)';
                Log::info("Price found on CheapShark: {$price}");
            } else {
                Log::info("No price found on CheapShark");
            }
        }

        if ($price !== null) {
            $game->update([
                'current_price' => $price,
                'price_source' => $source
            ]);
            
            $message = "Price updated via $source.";
            Log::info("Price refresh successful: {$message}");
            
            return [
                'price' => $price,
                'source' => $source,
                'message' => $message
            ];
        }

        Log::warning("Could not find price for game: {$game->title} from any source");
        return null;
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
