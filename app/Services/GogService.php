<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GogService
{
    protected $baseUrl = 'https://catalog.gog.com/v1/catalog';

    /**
     * Get game price from GOG Catalog
     * 
     * @param string $title
     * @return float|null
     */
    public function getPrice($title)
    {
        try {
            $response = Http::get($this->baseUrl, [
                'limit' => 1,
                'search' => $title,
                'countryCode' => 'GB',
                'currencyCode' => 'GBP',
                'order' => 'desc:score',
                'productType' => 'inGame'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data['products'])) {
                    $product = $data['products'][0];
                    
                    // Simple fuzzy match check could be added here if needed
                    // For now, we return the first result's price
                    
                    if (isset($product['price']['finalMoney']['amount'])) {
                        return (float) $product['price']['finalMoney']['amount'];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail to allow fallback to other services
            return null;
        }

        return null;
    }
}
