<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IgdbService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl = 'https://api.igdb.com/v4';

    public function __construct()
    {
        $this->clientId = config('services.igdb.client_id');
        $this->clientSecret = config('services.igdb.client_secret');
    }

    protected function getAccessToken()
    {
        if (!$this->clientId || !$this->clientSecret) {
            return null;
        }

        return Cache::remember('igdb_token', 3600, function () {
            $response = Http::post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            return null;
        });
    }

    public function searchGames($query)
    {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])->withBody(
            'search "' . $query . '"; fields name, cover.url, first_release_date, total_rating, summary, genres.name, platforms.name, aggregated_rating; limit 10;',
            'text/plain'
        )->post("{$this->baseUrl}/games");

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    public function getGameDetails($id)
    {
        $token = $this->getAccessToken();
        if (!$token) return null;

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])->withBody(
            "fields name, cover.url, first_release_date, total_rating, summary, genres.name, platforms.name, aggregated_rating; where id = {$id};",
            'text/plain'
        )->post("{$this->baseUrl}/games");

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data)) {
                return $this->formatGameDetails($data[0]);
            }
        }

        return null;
    }

    protected function formatGameDetails($data)
    {
        $imageUrl = null;
        if (isset($data['cover']['url'])) {
            // IGDB returns thumbnails by default (t_thumb). We want high quality.
            // e.g., //images.igdb.com/igdb/image/upload/t_thumb/co1r7h.jpg
            $imageUrl = 'https:' . str_replace('t_thumb', 't_cover_big', $data['cover']['url']);
        }

        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'metacritic' => isset($data['aggregated_rating']) ? round($data['aggregated_rating']) : null,
            'released' => isset($data['first_release_date']) ? date('Y-m-d', $data['first_release_date']) : null,
            'genres' => isset($data['genres']) ? array_map(fn($g) => ['name' => $g['name']], $data['genres']) : [],
            'rating' => isset($data['total_rating']) ? round($data['total_rating'] / 20, 2) : null, // Convert 100 scale to 5 scale
            'background_image' => $imageUrl,
            'summary' => $data['summary'] ?? null,
            'source' => 'igdb'
        ];
    }
}
