<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Platform;
use App\Models\CatalogGame;
use App\Services\RawgService;
use App\Services\IgdbService;
use App\Services\GamePriceManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class GameController extends Controller
{
    protected $rawgService;
    protected $igdbService;
    protected $gamePriceManager;

    public function __construct(
        RawgService $rawgService,
        IgdbService $igdbService,
        GamePriceManager $gamePriceManager
    ) {
        $this->rawgService = $rawgService;
        $this->igdbService = $igdbService;
        $this->gamePriceManager = $gamePriceManager;
    }

    public function index(Request $request)
    {
        $query = Game::query()
            ->with('platform')
            ->where('user_id', $request->user()->id);

        if ($request->search) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->platform_id) {
            $query->where('platform_id', $request->platform_id);
        }
        
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $sortField = $request->input('order_by', 'created_at');
        $sortDirection = $request->input('direction', 'desc');

        // Allow sorting by valid columns
        if (in_array($sortField, ['title', 'price', 'current_price', 'metascore', 'released_at', 'created_at'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = $request->input('per_page', 24);
        if ($perPage === 'all') {
             $games = $query->paginate(1000)->withQueryString();
        } else {
             $games = $query->paginate((int)$perPage)->withQueryString();
        }

        // Calculate totals for the header (only for current user's full collection if no filters, or filtered?)
        // Usually totals are nice to see for the current view, but the request was "Collection Value" which implies everything.
        // Let's pass global totals as well.
        $totalCost = Game::where('user_id', $request->user()->id)->sum('price');
        $totalValue = Game::where('user_id', $request->user()->id)->sum('current_price');

        // Calculate counts for sidebar
        $userId = $request->user()->id;
        $statusCounts = Game::where('user_id', $userId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $counts = [
            'all' => Game::where('user_id', $userId)->count(),
            'uncategorized' => $statusCounts['uncategorized'] ?? 0,
            'currently_playing' => $statusCounts['currently_playing'] ?? 0,
            'completed' => $statusCounts['completed'] ?? 0,
            'played' => $statusCounts['played'] ?? 0,
            'not_played' => $statusCounts['not_played'] ?? 0,
        ];

        return Inertia::render('Games/Index', [
            'games' => $games,
            'filters' => $request->only(['search', 'platform_id', 'status', 'order_by', 'direction', 'per_page']),
            'platforms' => Platform::all(),
            'totalCost' => $totalCost,
            'totalValue' => $totalValue,
            'counts' => $counts,
        ]);
    }

    public function statistics(Request $request)
    {
        $userId = $request->user()->id;

        // Summary Stats
        $totalGames = Game::where('user_id', $userId)->count();
        $totalSpent = Game::where('user_id', $userId)->sum('price');
        $totalValue = Game::where('user_id', $userId)->sum('current_price');

        // Platform Breakdown
        $platformStats = Game::where('user_id', $userId)
            ->join('platforms', 'games.platform_id', '=', 'platforms.id')
            ->selectRaw('platforms.name as platform_name, count(*) as count, sum(current_price) as total_value, sum(price) as total_spent')
            ->groupBy('platforms.name')
            ->orderByDesc('total_value')
            ->get();

        // Status Breakdown
        $statusStats = Game::where('user_id', $userId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        // Top Valued Games
        $topValuedGames = Game::where('user_id', $userId)
            ->whereNotNull('current_price')
            ->with('platform')
            ->orderByDesc('current_price')
            ->take(5)
            ->get();

        return Inertia::render('Games/Statistics', [
            'stats' => [
                'totalGames' => $totalGames,
                'totalValue' => $totalValue,
                'platforms' => $platformStats,
                'statuses' => $statusStats,
                'topValued' => $topValuedGames,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'platform_id' => 'required|exists:platforms,id',
            'price' => 'nullable|numeric|min:0',
            'current_price' => 'nullable|numeric|min:0',
            'price_source' => 'nullable|string|max:255',
            'purchase_location' => 'nullable|string|max:255',
            'purchased' => 'boolean',
            'image_url' => 'nullable|string',
            'image_file' => 'nullable|image|max:2048',
            // Metadata for Catalog & Details
            'rawg_id' => 'nullable|integer',
            'released' => 'nullable|date',
            'rating' => 'nullable|numeric',
            'metascore' => 'nullable|integer',
            'genres' => 'nullable|string',
            'status' => 'nullable|in:uncategorized,currently_playing,completed,played,not_played',
        ]);

        // Handle Catalog Logic
        if (!empty($validated['rawg_id'])) {
            $catalogGame = CatalogGame::firstOrCreate(
                ['rawg_id' => $validated['rawg_id']],
                [
                    'title' => $validated['title'],
                    'image_url' => $validated['image_url'],
                    'release_date' => $validated['released'] ?? null,
                    'rating' => $validated['rating'] ?? null,
                ]
            );
            $validated['catalog_game_id'] = $catalogGame->id;
        }

        // Map 'released' from form to 'released_at' in db if present
        if (isset($validated['released'])) {
            $validated['released_at'] = $validated['released'];
            unset($validated['released']);
        }
        
        // Default status
        if (!isset($validated['status'])) {
            $validated['status'] = 'uncategorized';
        }

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('games', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        $request->user()->games()->create($validated);

        return redirect()->back();
    }

    public function importSteam(Request $request)
    {
        $user = $request->user();
        $steamAccount = $user->linkedAccounts()->where('provider_name', 'steam')->first();

        if (!$steamAccount) {
            return redirect()->back()->withErrors(['steam' => 'Steam account not linked.']);
        }

        $apiKey = config('services.steam.client_secret');
        if (!$apiKey) {
             return redirect()->back()->withErrors(['steam' => 'Steam API Key missing on server.']);
        }

        // Fetch Owned Games
        // https://developer.valvesoftware.com/wiki/Steam_Web_API#GetOwnedGames_.28v0001.29
        $response = Http::get('http://api.steampowered.com/IPlayerService/GetOwnedGames/v0001/', [
            'key' => $apiKey,
            'steamid' => $steamAccount->provider_id,
            'include_appinfo' => true,
            'include_played_free_games' => true,
            'format' => 'json',
        ]);

        if ($response->failed()) {
             return redirect()->back()->withErrors(['steam' => 'Failed to fetch games from Steam.']);
        }

        $games = $response->json()['response']['games'] ?? [];
        $platform = Platform::firstOrCreate(['slug' => 'pc'], ['name' => 'PC']);

        $count = 0;
        foreach ($games as $steamGame) {
            // Basic Check to avoid duplicates (naive check by title for now)
            // Ideally we store steam_appid in the games table or catalog
            $exists = $user->games()->where('title', $steamGame['name'])->exists();
            
            if (!$exists) {
                $user->games()->create([
                    'title' => $steamGame['name'],
                    'platform_id' => $platform->id,
                    'steam_appid' => $steamGame['appid'],
                    'purchased' => true,
                    'image_url' => "http://media.steampowered.com/steamcommunity/public/images/apps/{$steamGame['appid']}/{$steamGame['img_icon_url']}.jpg",
                    // We could also map to catalog here if we had a mapping service
                ]);
                $count++;
            }
        }

        return redirect()->back()->with('success', "Imported {$count} games from Steam.");
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'games' => 'required|array',
            'games.*.title' => 'required|string|max:255',
            'games.*.platform_id' => 'required|exists:platforms,id',
            'games.*.price' => 'nullable|numeric|min:0',
            'games.*.current_price' => 'nullable|numeric|min:0',
            'games.*.purchase_location' => 'nullable|string|max:255',
            'games.*.purchased' => 'boolean',
        ]);

        foreach ($validated['games'] as $gameData) {
            $request->user()->games()->create($gameData);
        }

        return redirect()->back()->with('message', count($validated['games']) . ' games added successfully.');
    }
    
    public function update(Request $request, Game $game)
    {
        if ($request->user()->id !== $game->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'platform_id' => 'required|exists:platforms,id',
            'price' => 'nullable|numeric|min:0',
            'current_price' => 'nullable|numeric|min:0',
            'price_source' => 'nullable|string|max:255',
            'purchase_location' => 'nullable|string|max:255',
            'purchased' => 'boolean',
            'image_url' => 'nullable|string',
            'image_file' => 'nullable|image|max:2048',
            // Metadata
            'rawg_id' => 'nullable|integer',
            'released' => 'nullable|date',
            'rating' => 'nullable|numeric',
            'metascore' => 'nullable|integer',
            'genres' => 'nullable|string',
            'status' => 'nullable|in:uncategorized,currently_playing,completed,played,not_played',
        ]);

        // Map 'released' from form to 'released_at' in db if present
        if (isset($validated['released'])) {
            $validated['released_at'] = $validated['released'];
            unset($validated['released']);
        }

        if ($request->hasFile('image_file')) {
            // Delete old image if it's local
            if ($game->image_url && str_starts_with($game->image_url, '/storage/')) {
                 $oldPath = str_replace('/storage/', '', $game->image_url);
                 Storage::disk('public')->delete($oldPath);
            }
            
            $path = $request->file('image_file')->store('games', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        $game->update($validated);

        return redirect()->back();
    }

    public function refreshPrice(Request $request, Game $game)
    {
        if ($request->user()->id !== $game->user_id) {
            abort(403);
        }

        $result = $this->gamePriceManager->updateGamePrice($game);

        if ($result) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $result['message'], 
                    'current_price' => $result['price'],
                    'source' => $result['source']
                ]);
            }
            return redirect()->back()->with('success', $result['message']);
        }

        Log::warning("Could not find price for game: {$game->title} from any source");
        $errorMsg = 'Could not fetch price details from available sources.';
        
        $missingKeys = [];
        if (!config('services.pricecharting.key') || str_contains(config('services.pricecharting.key'), 'your_')) {
            $missingKeys[] = 'PriceCharting';
        }
        if (!config('services.ebay.client_id') || str_contains(config('services.ebay.client_id'), 'your_')) {
            $missingKeys[] = 'eBay';
        }

        if (!empty($missingKeys)) {
            $errorMsg .= ' (Missing/Invalid keys for: ' . implode(', ', $missingKeys) . ')';
        }

        if ($request->wantsJson()) {
            return response()->json(['error' => $errorMsg], 422);
        }
        
        return redirect()->back()->with('error', $errorMsg);
    }

    public function refreshMetadata(Request $request, Game $game)
    {
        if ($request->user()->id !== $game->user_id) {
            abort(403);
        }

        $details = null;
        $source = null;

        // 1. Try by RAWG ID if we have it
        if ($game->rawg_id) {
            $details = $this->rawgService->getGameDetails($game->rawg_id);
            if ($details) $source = 'RAWG';
        }

        // 2. Try by IGDB ID if we have it and RAWG failed or wasn't set
        if (!$details && $game->igdb_id) {
            $details = $this->igdbService->getGameDetails($game->igdb_id);
            if ($details) $source = 'IGDB';
        }

        // 3. If no details yet, try searching by title on RAWG
        if (!$details) {
            $searchResults = $this->rawgService->searchGames($game->title);
            
            if (!empty($searchResults) && isset($searchResults[0])) {
                $bestMatch = $searchResults[0];
                $details = $this->rawgService->getGameDetails($bestMatch['id']);
                if ($details) $source = 'RAWG';
            }
        }

        // 4. If still no details, try searching by title on IGDB
        if (!$details) {
            $searchResults = $this->igdbService->searchGames($game->title);

            if (!empty($searchResults) && isset($searchResults[0])) {
                $bestMatch = $searchResults[0];
                $details = $this->igdbService->getGameDetails($bestMatch['id']);
                if ($details) $source = 'IGDB';
            }
        }

        if (!$details) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Could not find game details on RAWG or IGDB.'], 404);
            }
            return redirect()->back()->withErrors(['message' => 'Could not find game details on RAWG or IGDB.']);
        }

        // 5. Update Game
        $updateData = [
            'title' => $details['name'],
            'metascore' => $details['metacritic'] ?? null,
            'released_at' => $details['released'] ?? null,
            'genres' => isset($details['genres']) ? implode(', ', array_column($details['genres'], 'name')) : null,
            'rating' => $details['rating'] ?? null,
        ];

        if ($source === 'RAWG') {
            $updateData['rawg_id'] = $details['id'];
        } elseif ($source === 'IGDB') {
            $updateData['igdb_id'] = $details['id'];
        }
        
        // Only update image if it's missing
        if (empty($game->image_url) && !empty($details['background_image'])) {
            $updateData['image_url'] = $details['background_image'];
        }

        $game->update($updateData);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Game details updated.', 'game' => $game]);
        }

        return redirect()->back()->with('success', "Game details updated from {$source}.");
    }

    public function destroy(Request $request, Game $game)
    {
        if ($request->user()->id !== $game->user_id) {
            abort(403);
        }

        $game->delete();

        return redirect()->back();
    }
}
