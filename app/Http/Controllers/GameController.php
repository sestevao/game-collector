<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Platform;
use App\Models\CatalogGame;
use App\Services\RawgService;
use App\Services\PriceChartingService;
use App\Services\GogService;
use App\Services\SteamService;
use App\Services\PlaystationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GameController extends Controller
{
    protected $rawgService;
    protected $priceChartingService;
    protected $gogService;
    protected $steamService;
    protected $playstationService;

    public function __construct(
        RawgService $rawgService, 
        PriceChartingService $priceChartingService, 
        GogService $gogService,
        SteamService $steamService,
        PlaystationService $playstationService
    ) {
        $this->rawgService = $rawgService;
        $this->priceChartingService = $priceChartingService;
        $this->gogService = $gogService;
        $this->steamService = $steamService;
        $this->playstationService = $playstationService;
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

        return Inertia::render('Games/Index', [
            'games' => $games,
            'filters' => $request->only(['search', 'platform_id', 'status', 'order_by', 'direction', 'per_page']),
            'platforms' => Platform::all(),
            'totalCost' => $totalCost,
            'totalValue' => $totalValue,
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

        $price = null;
        $source = '';

        // 1. Try PriceCharting (Best for Console/Physical Collection Value)
        // Only if configured and price not found yet
        if (config('services.pricecharting.key')) {
            $game->load('platform');
            $pcPrice = $this->priceChartingService->getPrice($game->title, $game->platform->name ?? null);
            if ($pcPrice !== null) {
                $price = $pcPrice;
                $source = 'PriceCharting (Value)';
            }
        }

        // 2. Try PlayStation Store if platform matches
        if ($price === null && $game->platform) {
            $platName = strtolower($game->platform->name);
            $platSlug = strtolower($game->platform->slug);
            if (str_contains($platName, 'playstation') || str_contains($platName, 'ps') || str_contains($platSlug, 'ps')) {
                $psPrice = $this->playstationService->getPrice($game->title);
                if ($psPrice !== null) {
                    $price = $psPrice;
                    $source = 'PlayStation Store';
                }
            }
        }

        // 3. Try Steam Store API if AppID exists and no price yet
        if ($price === null && $game->steam_appid) {
            $steamPrice = $this->steamService->getPrice($game->steam_appid);
            if ($steamPrice !== null) {
                $price = $steamPrice;
                $source = 'Steam';
            }
        }

        // 3. Try Steam Search if no price yet (and PC platform)
        if ($price === null && $game->platform && $game->platform->slug === 'pc') {
            $steamData = $this->steamService->searchAndGetPrice($game->title);
            if ($steamData) {
                $price = $steamData['price'];
                $source = 'Steam (Search)';
                // Optionally save the AppID for future use
                if (!$game->steam_appid) {
                    $game->steam_appid = $steamData['appid'];
                    // $game->save(); // Will be saved at the end with price update
                }
            }
        }

        // 4. Try GOG API
        if ($price === null) {
            $gogPrice = $this->gogService->getPrice($game->title);
            if ($gogPrice !== null) {
                $price = $gogPrice;
                $source = 'GOG';
            }
        }

        // 4. Try CheapShark API if no price found yet
        if ($price === null) {
            $cheapSharkPrice = $this->getCheapSharkPrice($game->title);
            if ($cheapSharkPrice !== null) {
                // CheapShark is in USD, convert to GBP (Approximate rate: 0.82)
                $price = $cheapSharkPrice * 0.82; 
                $source = 'CheapShark (Est.)';
            }
        }

        if ($price !== null) {
            $game->update([
                'current_price' => $price
            ]);
            
            $message = "Price updated via $source.";

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message, 
                    'current_price' => $game->current_price,
                    'source' => $source
                ]);
            }
            return redirect()->back()->with('success', $message);
        }

        $errorMsg = 'Could not fetch price details from available sources.';
        
        // Add hint if PriceCharting is not configured and it's a console game
        if (!config('services.pricecharting.key') && $game->platform && $game->platform->slug !== 'pc') {
             $errorMsg .= ' (PriceCharting API Key is missing, which is required for console games)';
        }

        if ($request->wantsJson()) {
            return response()->json(['error' => $errorMsg], 422);
        }
        
        // Return with 'error' flash message instead of validation errors for better UI handling
        return redirect()->back()->with('error', $errorMsg);
    }

    private function getCheapSharkPrice($title)
    {
        try {
            $response = Http::get("https://www.cheapshark.com/api/1.0/games", [
                'title' => $title,
                'limit' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data)) {
                    // CheapShark returns 'cheapest' as a string, e.g., "19.99"
                    return (float) $data[0]['cheapest'];
                }
            }
        } catch (\Exception $e) {
            // Log error or ignore
        }
        return null;
    }

    public function refreshMetadata(Request $request, Game $game)
    {
        if ($request->user()->id !== $game->user_id) {
            abort(403);
        }

        $details = null;

        // 1. Try by RAWG ID if we have it
        if ($game->rawg_id) {
            $details = $this->rawgService->getGameDetails($game->rawg_id);
        }

        // 2. If no ID or failed, try searching by title
        if (!$details) {
            $searchResults = $this->rawgService->searchGames($game->title);
            
            if (!empty($searchResults) && isset($searchResults[0])) {
                $bestMatch = $searchResults[0];
                $details = $this->rawgService->getGameDetails($bestMatch['id']);
            }
        }

        if (!$details) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Could not find game details on RAWG.'], 404);
            }
            return redirect()->back()->withErrors(['message' => 'Could not find game details on RAWG.']);
        }

        // 3. Update Game
        $updateData = [
            'title' => $details['name'],
            'rawg_id' => $details['id'],
            'metascore' => $details['metacritic'] ?? null,
            'released_at' => $details['released'] ?? null,
            'genres' => isset($details['genres']) ? implode(', ', array_column($details['genres'], 'name')) : null,
            'rating' => $details['rating'] ?? null,
        ];
        
        // Only update image if it's missing
        if (empty($game->image_url) && !empty($details['background_image'])) {
            $updateData['image_url'] = $details['background_image'];
        }

        $game->update($updateData);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Game details updated.', 'game' => $game]);
        }

        return redirect()->back()->with('success', 'Game details updated from RAWG.');
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
