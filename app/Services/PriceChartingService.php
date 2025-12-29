<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceChartingService
{
    protected $apiKey;
    protected $baseUrl = 'https://www.pricecharting.com/api/products';

    public function __construct()
    {
        $this->apiKey = config('services.pricecharting.key');
    }

    public function getPrice($title, $platformName = null)
    {
        if (!$this->apiKey) {
            Log::warning('PriceChartingService: API key is missing or not configured.');
            return null;
        }

        try {
            Log::info("PriceChartingService: Searching for '{$title}'" . ($platformName ? " on platform '{$platformName}'" : ""));

            $response = Http::get($this->baseUrl, [
                't' => $this->apiKey,
                'q' => $title,
            ]);

            if ($response->successful()) {
                $products = $response->json();
                
                if (isset($products['status']) && $products['status'] === 'fail') {
                     Log::warning("PriceChartingService: API returned fail status for '{$title}'. Message: " . ($products['message'] ?? 'Unknown'));
                     return null;
                }

                // If platform is provided, try to match it
                if ($platformName && !empty($products) && is_array($products)) {
                    // Normalize platform name for better matching
                    $platformName = strtolower($platformName);
                    
                    // First pass: Exact or close match
                    foreach ($products as $product) {
                        $console = strtolower($product['console-name'] ?? '');
                        if ($console === $platformName || 
                            str_contains($console, $platformName) || 
                            str_contains($platformName, $console)) {
                            Log::info("PriceChartingService: Match found for platform '{$platformName}': {$product['product-name']} ({$product['console-name']})");
                            return $this->extractPrice($product);
                        }
                    }
                    Log::info("PriceChartingService: No specific platform match found for '{$platformName}'. Falling back to first result.");
                }
                
                // Fallback: Return the first result if exists
                if (!empty($products) && is_array($products) && isset($products[0])) {
                    Log::info("PriceChartingService: Using first result: {$products[0]['product-name']} ({$products[0]['console-name']})");
                    return $this->extractPrice($products[0]);
                } else {
                    Log::info("PriceChartingService: No products found for '{$title}'");
                }
            } else {
                Log::error("PriceChartingService: API request failed with status {$response->status()}");
            }
        } catch (\Exception $e) {
            Log::error("PriceChartingService: Exception encountered: " . $e->getMessage());
        }

        return null;
    }

    protected function extractPrice($product)
    {
        // Prices are in cents (USD)
        // We prefer CIB (Complete in Box), then Loose, then New
        $priceInCents = 0;
        $type = 'None';

        if (isset($product['cib-price']) && $product['cib-price'] > 0) {
            $priceInCents = $product['cib-price'];
            $type = 'CIB';
        } elseif (isset($product['loose-price']) && $product['loose-price'] > 0) {
            $priceInCents = $product['loose-price'];
            $type = 'Loose';
        } elseif (isset($product['new-price']) && $product['new-price'] > 0) {
            $priceInCents = $product['new-price'];
            $type = 'New';
        }

        // Convert to dollars/units
        $priceUSD = $priceInCents / 100;

        // Convert to GBP (approximate fixed rate for now, 1 USD = 0.82 GBP)
        // Ideally this should be dynamic or configurable
        $priceGBP = round($priceUSD * 0.82, 2);

        Log::info("PriceChartingService: Extracted price: \${$priceUSD} USD -> £{$priceGBP} GBP (Type: {$type})");

        return $priceGBP;
    }
}
