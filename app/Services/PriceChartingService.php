<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
            return null;
        }

        try {
            $response = Http::get($this->baseUrl, [
                't' => $this->apiKey,
                'q' => $title,
            ]);

            if ($response->successful()) {
                $products = $response->json();
                
                if (isset($products['status']) && $products['status'] === 'fail') {
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
                            return $this->extractPrice($product);
                        }
                    }
                }
                
                // Fallback: Return the first result if exists
                if (!empty($products) && is_array($products) && isset($products[0])) {
                    return $this->extractPrice($products[0]);
                }
            }
        } catch (\Exception $e) {
            // Log error if needed
        }

        return null;
    }

    protected function extractPrice($product)
    {
        // Prices are in cents (USD)
        // We prefer CIB (Complete in Box), then Loose, then New
        $priceInCents = 0;

        if (isset($product['cib-price']) && $product['cib-price'] > 0) {
            $priceInCents = $product['cib-price'];
        } elseif (isset($product['loose-price']) && $product['loose-price'] > 0) {
            $priceInCents = $product['loose-price'];
        } elseif (isset($product['new-price']) && $product['new-price'] > 0) {
            $priceInCents = $product['new-price'];
        }

        // Convert to dollars/units
        $priceUSD = $priceInCents / 100;

        // Convert to GBP (approximate fixed rate for now, 1 USD = 0.82 GBP)
        // Ideally this should be dynamic or configurable
        return round($priceUSD * 0.82, 2);
    }
}
