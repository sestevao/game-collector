<?php

namespace App\Http\Controllers;

use App\Services\RawgService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Platform;

class RankingController extends Controller
{
    protected $rawgService;

    public function __construct(RawgService $rawgService)
    {
        $this->rawgService = $rawgService;
    }

    private function processGamesResult($result)
    {
        if (isset($result['error'])) {
            return ['games' => [], 'error' => $result['error']];
        }
        return ['games' => $result, 'error' => null];
    }

    public function bestOfYear(Request $request)
    {
        $currentYear = date('Y');
        
        $params = [
            'dates' => "{$currentYear}-01-01,{$currentYear}-12-31",
            'page_size' => 24,
        ];

        // Ordering
        $ordering = $request->input('ordering', '-metacritic');
        $params['ordering'] = $ordering;

        // Platform
        $platform = $request->input('platform');
        if ($platform) {
            $params['platforms'] = $platform;
        }

        $result = $this->rawgService->getGames($params);

        $data = $this->processGamesResult($result);

        // Fetch RAWG platforms for filter
        // We might want to cache this or hardcode common ones to save API calls
        $filterPlatforms = $this->rawgService->getPlatforms();

        return Inertia::render('Games/Ranking', [
            'title' => "Best of {$currentYear}",
            'games' => $data['games'],
            'error' => $data['error'],
            'platforms' => Platform::all(), // Local platforms for "Add to Library"
            'filterPlatforms' => $filterPlatforms,
            'filters' => [
                'ordering' => $ordering,
                'platform' => $platform
            ]
        ]);
    }

    public function popular2024(Request $request)
    {
        $year = 2024;
        
        $params = [
            'dates' => "{$year}-01-01,{$year}-12-31",
            'page_size' => 24,
        ];

        // Ordering
        $ordering = $request->input('ordering', '-added');
        $params['ordering'] = $ordering;

        // Platform
        $platform = $request->input('platform');
        if ($platform) {
            $params['platforms'] = $platform;
        }

        $result = $this->rawgService->getGames($params);

        $data = $this->processGamesResult($result);

        $filterPlatforms = $this->rawgService->getPlatforms();

        return Inertia::render('Games/Ranking', [
            'title' => "Popular in {$year}",
            'games' => $data['games'],
            'error' => $data['error'],
            'platforms' => Platform::all(),
            'filterPlatforms' => $filterPlatforms,
            'filters' => [
                'ordering' => $ordering,
                'platform' => $platform
            ]
        ]);
    }

    public function top250(Request $request)
    {
        $params = [
            'page_size' => 24,
        ];

        // Ordering
        $ordering = $request->input('ordering', '-metacritic');
        $params['ordering'] = $ordering;

        // Platform
        $platform = $request->input('platform');
        if ($platform) {
            $params['platforms'] = $platform;
        }

        $result = $this->rawgService->getGames($params);

        $data = $this->processGamesResult($result);

        $filterPlatforms = $this->rawgService->getPlatforms();

        return Inertia::render('Games/Ranking', [
            'title' => "All time top 250",
            'games' => $data['games'],
            'error' => $data['error'],
            'platforms' => Platform::all(),
            'filterPlatforms' => $filterPlatforms,
            'filters' => [
                'ordering' => $ordering,
                'platform' => $platform
            ]
        ]);
    }

    public function browse(Request $request)
    {
        $params = [
            'page_size' => 24,
        ];

        if ($request->has('platform')) {
            $params['platforms'] = $request->platform;
        }
        if ($request->has('store')) {
            $params['stores'] = $request->store;
        }
        if ($request->has('genres')) {
            $params['genres'] = $request->genres;
        }
        
        // Add ordering if needed, e.g. -released or -added
        $params['ordering'] = '-added';

        $result = $this->rawgService->getGames($params);
        $data = $this->processGamesResult($result);

        return Inertia::render('Games/Ranking', [
            'title' => "Browse Games",
            'games' => $data['games'],
            'error' => $data['error'],
            'platforms' => Platform::all(),
        ]);
    }

    public function platforms()
    {
        $items = $this->rawgService->getPlatforms();
        return Inertia::render('Games/CategoryIndex', [
            'title' => 'Platforms',
            'items' => $items,
            'type' => 'platform',
        ]);
    }

    public function stores()
    {
        $items = $this->rawgService->getStores();
        return Inertia::render('Games/CategoryIndex', [
            'title' => 'Stores',
            'items' => $items,
            'type' => 'store',
        ]);
    }

    public function collections()
    {
        $items = $this->rawgService->getGenres();
        return Inertia::render('Games/CategoryIndex', [
            'title' => 'Genres',
            'items' => $items,
            'type' => 'genres',
        ]);
    }
}
