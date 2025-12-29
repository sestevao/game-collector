<?php

namespace App\Http\Controllers;

use App\Services\RawgService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $rawgService;

    public function __construct(RawgService $rawgService)
    {
        $this->rawgService = $rawgService;
    }

    public function index(Request $request)
    {
        // Default to "relevance" which we map to RAWG's ordering
        $ordering = '-added'; // Default popular
        if ($request->has('ordering')) {
            $map = [
                'relevance' => '-added', // Most added ~ relevant/popular
                'rating' => '-rating',
                'released' => '-released',
            ];
            $ordering = $map[$request->ordering] ?? '-added';
        }

        $params = [
            'ordering' => $ordering,
            'page_size' => 12,
        ];
        
        // Handle Platform filtering if needed in future
        // if ($request->platform && $request->platform !== 'all') { ... }

        $games = $this->rawgService->getGames($params);

        return Inertia::render('Dashboard', [
            'rawgGames' => $games,
            'filters' => $request->only(['ordering', 'platform']),
        ]);
    }
}
