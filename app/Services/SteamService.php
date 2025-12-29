<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SteamService
{
    protected $baseUrl = 'https://store.steampowered.com/api';

    /**
     * Get price for a specific App ID
     */
    public function getPrice($appId)
    {
        try {
            $response = Http::get("{$this->baseUrl}/appdetails", [
                'appids' => $appId,
                'cc' => 'GB', // Currency Code: GBP
                'l' => 'en'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data[$appId]['data']['price_overview'])) {
                    $priceData = $data[$appId]['data']['price_overview'];
                    return $priceData['final'] / 100;
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Search for a game by title and return price (and AppID)
     */
    public function searchAndGetPrice($title)
    {
        try {
            // Use Steam Store Search API
            $response = Http::get("{$this->baseUrl}/storesearch", [
                'term' => $title,
                'l' => 'en',
                'cc' => 'GB'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data['items'])) {
                    $item = $data['items'][0]; // Best match
                    
                    // Return price if available
                    if (isset($item['price'])) {
                        return [
                            'price' => $item['price']['final'] / 100,
                            'appid' => $item['id'],
                            'url' => "https://store.steampowered.com/app/{$item['id']}"
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }
}
