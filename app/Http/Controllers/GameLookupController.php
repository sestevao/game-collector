<?php

namespace App\Http\Controllers;

use App\Services\RawgService;
use App\Services\GamePriceManager;
use App\Models\Platform;
use Illuminate\Http\Request;

class GameLookupController extends Controller
{
    protected $rawgService;
    protected $gamePriceManager;

    public function __construct(RawgService $rawgService, GamePriceManager $gamePriceManager)
    {
        $this->rawgService = $rawgService;
        $this->gamePriceManager = $gamePriceManager;
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        
        if (!$query) {
            return response()->json([]);
        }

        $results = $this->rawgService->searchGames($query);

        return response()->json($results);
    }

    public function details($id)
    {
        $details = $this->rawgService->getGameDetails($id);
        
        if (!$details) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        return response()->json($details);
    }

    public function searchPrices(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'platform_id' => 'nullable|exists:platforms,id',
            'refresh' => 'nullable|boolean',
        ]);

        $title = $request->input('title');
        $platformId = $request->input('platform_id');
        $forceRefresh = $request->boolean('refresh');
        $platformName = null;
        $platformSlug = null;

        if ($platformId) {
            $platform = Platform::find($platformId);
            if ($platform) {
                $platformName = $platform->name;
                $platformSlug = $platform->slug;
            }
        }

        $prices = $this->gamePriceManager->getMarketPrices($title, $platformName, $platformSlug, null, $forceRefresh);

        return response()->json([
            'prices' => $prices,
            'title' => $title,
            'platform' => $platformName
        ]);
    }
}
