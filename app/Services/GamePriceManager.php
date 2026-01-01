<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
     * Get market prices for a given title and platform.
     * 
     * @param string $title
     * @param string|null $platformName
     * @param string|null $platformSlug
     * @param int|null $steamAppId
     * @param bool $forceRefresh
     * @return array
     */
    public function getMarketPrices($title, $platformName = null, $platformSlug = null, $steamAppId = null, $forceRefresh = false)
    {
        // Create a unique cache key based on inputs
        $cacheKey = 'market_prices_' . md5(json_encode([
            'title' => $title, 
            'platform' => $platformName, 
            'slug' => $platformSlug, 
            'steam' => $steamAppId
        ]));

        // Return cached data if available and not forcing refresh
        if (!$forceRefresh && Cache::has($cacheKey)) {
            Log::info("Returning cached market prices for: {$title} ({$platformName})");
            return Cache::get($cacheKey);
        }

        Log::info("Fetching market prices for: {$title} ({$platformName})");

        $marketPrices = [];
        
        // Helper to add price
        $addPrice = function($source, $price, $metadata = []) use (&$marketPrices) {
            $marketPrices[] = [
                'source' => $source,
                'price' => (float) $price,
                'currency' => 'GBP',
                'fetched_at' => now()->toIso8601String(),
                'metadata' => $metadata,
            ];
        };

        // 1. PriceCharting
        if (config('services.pricecharting.key')) {
            try {
                $pcPrice = $this->priceChartingService->getPrice($title, $platformName);
                if ($pcPrice !== null) {
                    $addPrice('PriceCharting', $pcPrice);
                }
            } catch (\Exception $e) {
                Log::error("PriceCharting error: " . $e->getMessage());
            }
        }

        // 2. eBay
        try {
            $ebayPrice = $this->ebayService->getPrice($title, $platformName);
            if ($ebayPrice !== null) {
                $addPrice('eBay', $ebayPrice);
            }
        } catch (\Exception $e) {
            Log::error("eBay error: " . $e->getMessage());
        }

        // 3. CeX
        try {
            $cexPrice = $this->cexService->getPrice($title, $platformName);
            if ($cexPrice !== null) {
                $addPrice('CeX', $cexPrice);
            }
        } catch (\Exception $e) {
            Log::error("CeX error: " . $e->getMessage());
        }

        // 4. Amazon
        try {
            $amazonPrice = $this->amazonService->getPrice($title, $platformName);
            if ($amazonPrice !== null) {
                $addPrice('Amazon', $amazonPrice);
            }
        } catch (\Exception $e) {
            Log::error("Amazon error: " . $e->getMessage());
        }

        // 5. PlayStation Store
        if (in_array($platformSlug, ['playstation-5', 'playstation-4', 'ps5', 'ps4'])) {
            try {
                $psPrice = $this->playstationService->getPrice($title);
                if ($psPrice !== null) {
                    $addPrice('PlayStation Store', $psPrice);
                }
            } catch (\Exception $e) {
                Log::error("PS Store error: " . $e->getMessage());
            }
        }

        // 6. Steam
        if ($steamAppId) {
            try {
                $steamPrice = $this->steamService->getPrice($steamAppId);
                if ($steamPrice !== null) {
                    $addPrice('Steam', $steamPrice);
                }
            } catch (\Exception $e) {
                Log::error("Steam error: " . $e->getMessage());
            }
        } elseif ($platformSlug === 'pc' || $platformName === 'PC') {
            try {
                $steamData = $this->steamService->searchAndGetPrice($title);
                if ($steamData) {
                    $addPrice('Steam', $steamData['price'], ['appid' => $steamData['appid']]);
                }
            } catch (\Exception $e) {
                Log::error("Steam search error: " . $e->getMessage());
            }
        }

        // 7. GOG
        try {
            $gogPrice = $this->gogService->getPrice($title);
            if ($gogPrice !== null) {
                $addPrice('GOG', $gogPrice);
            }
        } catch (\Exception $e) {
            Log::error("GOG error: " . $e->getMessage());
        }

        // 8. CheapShark
        try {
            $cheapSharkPrice = $this->getCheapSharkPrice($title);
            if ($cheapSharkPrice !== null) {
                $priceGbp = $cheapSharkPrice * 0.82; // Rough conversion USD -> GBP
                $addPrice('CheapShark', $priceGbp);
            }
        } catch (\Exception $e) {
            Log::error("CheapShark error: " . $e->getMessage());
        }

        Cache::put($cacheKey, $marketPrices, now()->addHours(24));

        return $marketPrices;
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

        $game->loadMissing('platform');
        $platformName = $game->platform ? $game->platform->name : null;
        $platformSlug = $game->platform ? $game->platform->slug : null;

        $marketPrices = $this->getMarketPrices($game->title, $platformName, $platformSlug, $game->steam_appid);

        // Determine Best Price (Prioritize: PriceCharting > eBay > CeX > Amazon > Store APIs)
        $priorityOrder = ['PriceCharting', 'eBay', 'CeX', 'Amazon', 'PlayStation Store', 'Steam', 'GOG', 'CheapShark'];
        
        $bestPrice = null;
        $bestSource = '';

        foreach ($priorityOrder as $source) {
            foreach ($marketPrices as $priceData) {
                if ($priceData['source'] === $source) {
                    $bestPrice = $priceData['price'];
                    $bestSource = $source;
                    break 2;
                }
            }
        }

        // Fallback if no priority match
        if ($bestPrice === null && !empty($marketPrices)) {
            $bestPrice = $marketPrices[0]['price'];
            $bestSource = $marketPrices[0]['source'];
        }

        if ($bestPrice !== null) {
            $game->current_price = $bestPrice;
            $game->price_source = $bestSource;
            $game->market_prices = $marketPrices;

            // Update Steam App ID if found in any of the results
            foreach ($marketPrices as $price) {
                if ($price['source'] === 'Steam' && !empty($price['metadata']['appid'])) {
                    $game->steam_appid = $price['metadata']['appid'];
                }
            }

            $game->save();

            Log::info("Updated price for {$game->title}: {$bestPrice} (Source: {$bestSource})");
            
            return [
                'price' => $bestPrice,
                'source' => $bestSource,
                'message' => "Updated price to £{$bestPrice} from {$bestSource}"
            ];
        }

        return null;
    }

    protected function getCheapSharkPrice($title)
    {
        $response = Http::get('https://www.cheapshark.com/api/1.0/games', [
            'title' => $title,
            'limit' => 1
        ]);

        if ($response->successful() && !empty($response->json())) {
            return $response->json()[0]['cheapest'] ?? null;
        }

        return null;
    }
}
