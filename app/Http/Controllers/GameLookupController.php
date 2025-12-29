<?php

namespace App\Http\Controllers;

use App\Services\RawgService;
use Illuminate\Http\Request;

class GameLookupController extends Controller
{
    protected $rawgService;

    public function __construct(RawgService $rawgService)
    {
        $this->rawgService = $rawgService;
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
}
