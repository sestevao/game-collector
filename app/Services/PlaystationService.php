<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PlaystationService
{
    protected $baseUrl = 'https://store.playstation.com/en-gb/search/';

    public function getPrice($title)
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept-Language' => 'en-GB,en;q=0.9',
            ])->get($this->baseUrl . urlencode($title));

            if ($response->successful()) {
                // Extract __NEXT_DATA__
                if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/', $response->body(), $matches)) {
                    $json = json_decode($matches[1], true);
                    
                    // We need to find the products list.
                    // Since the structure can vary, we'll try to find the first object that looks like a product with a price.
                    // Based on the probe, we have 'basePrice' and 'name'.
                    
                    $price = $this->findPriceInJson($json, $title);
                    if ($price !== null) {
                        return $price;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error or ignore
            return null;
        }

        return null;
    }

    private function findPriceInJson($json, $searchTitle)
    {
        // Flatten the search a bit or use a recursive iterator to find the first valid product match
        // We prioritize exact matches or close matches if possible, but for now, let's take the first valid product price.
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($json),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $key => $value) {
            if (is_array($value) && (isset($value['basePrice']) || isset($value['price']))) {
                // Check if this object also has a name that matches roughly
                if (isset($value['name']) && is_string($value['name'])) {
                    // Simple check: if the game title is contained in the result name
                    // e.g. Search "Elden Ring", Result "ELDEN RING PS4 & PS5"
                    if (stripos($value['name'], $searchTitle) !== false) {
                        $priceString = $value['basePrice'] ?? ($value['price']['basePrice'] ?? null);
                        
                        // Check for discounted price if available
                        if (isset($value['discountedPrice'])) {
                            $priceString = $value['discountedPrice'];
                        }

                        if ($priceString) {
                            return $this->parsePrice($priceString);
                        }
                    }
                }
            }
        }

        return null;
    }

    private function parsePrice($priceString)
    {
        // Remove currency symbols and non-numeric chars except dot
        // £49.99 -> 49.99
        // €49.99 -> 49.99
        // Free -> 0
        if (stripos($priceString, 'free') !== false) {
            return 0.0;
        }
        
        return (float) preg_replace('/[^0-9.]/', '', $priceString);
    }
}
