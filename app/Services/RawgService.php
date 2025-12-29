<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RawgService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.rawg.key');
        $this->baseUrl = config('services.rawg.base_url');
    }

    public function getGames($params = [])
    {
        if (!$this->apiKey) {
            return ['error' => 'API Key is missing'];
        }

        $defaultParams = [
            'key' => $this->apiKey,
            'page_size' => 12,
        ];

        $response = Http::get("{$this->baseUrl}/games", array_merge($defaultParams, $params));

        if ($response->failed()) {
            return [];
        }

        return $response->json()['results'] ?? [];
    }

    public function searchGames($query)
    {
        if (!$this->apiKey) {
            return ['error' => 'API Key is missing. Please configure RAWG_API_KEY in .env'];
        }

        $response = Http::get("{$this->baseUrl}/games", [
            'key' => $this->apiKey,
            'search' => $query,
            'page_size' => 10,
        ]);

        if ($response->failed()) {
            return ['error' => 'Failed to connect to RAWG API'];
        }

        return $response->json()['results'] ?? [];
    }

    public function getGameDetails($id)
    {
        if (!$this->apiKey) {
            return null;
        }

        $response = Http::get("{$this->baseUrl}/games/{$id}", [
            'key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    public function getPlatforms()
    {
        if (!$this->apiKey) return [];
        $response = Http::get("{$this->baseUrl}/platforms", ['key' => $this->apiKey, 'page_size' => 40]);
        return $response->json()['results'] ?? [];
    }

    public function getStores()
    {
        if (!$this->apiKey) return [];
        $response = Http::get("{$this->baseUrl}/stores", ['key' => $this->apiKey]);
        return $response->json()['results'] ?? [];
    }

    public function getGenres()
    {
        if (!$this->apiKey) return [];
        $response = Http::get("{$this->baseUrl}/genres", ['key' => $this->apiKey, 'page_size' => 40]);
        return $response->json()['results'] ?? [];
    }
}
