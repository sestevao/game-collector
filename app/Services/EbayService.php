<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class EbayService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl = 'https://api.ebay.com/buy/browse/v1/item_summary/search';
    protected $authUrl = 'https://api.ebay.com/identity/v1/oauth2/token';

    public function __construct()
    {
        $this->clientId = config('services.ebay.client_id');
        $this->clientSecret = config('services.ebay.client_secret');
    }

    public function getPrice($title, $platform = null)
    {
        if (!$this->clientId || !$this->clientSecret || 
            str_contains($this->clientId, 'your_') || 
            str_contains($this->clientSecret, 'your_')) {
            return null;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $query = $title;
        if ($platform) {
            $query .= " " . $platform;
        }

        try {
            // We target EBAY_GB for this user since they seem to use GBP
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_GB', 
            ])->get($this->baseUrl, [
                'q' => $query,
                'limit' => 5,
                'filter' => 'buyingOptions:{FIXED_PRICE}', // Buy It Now
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['itemSummaries']) && !empty($data['itemSummaries'])) {
                    $prices = [];
                    foreach ($data['itemSummaries'] as $item) {
                        if (isset($item['price']['value'])) {
                            $price = (float) $item['price']['value'];
                            $currency = $item['price']['currency'] ?? 'GBP';
                            
                            // Simple conversion if needed (assuming base is GBP)
                            if ($currency === 'USD') {
                                $price *= 0.82;
                            } elseif ($currency === 'EUR') {
                                $price *= 0.86;
                            }
                            
                            $prices[] = $price;
                        }
                    }
                    
                    if (count($prices) > 0) {
                        // Average of top 5 relevant results
                        $avg = array_sum($prices) / count($prices);
                        return round($avg, 2);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error
        }

        return null;
    }

    protected function getAccessToken()
    {
        return Cache::remember('ebay_access_token', 7000, function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->authUrl, [
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://api.ebay.com/oauth/api_scope'
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }
            return null;
        });
    }
}
