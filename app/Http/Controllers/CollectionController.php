<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CollectionController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $collection = Auth::user()->collections()->create($request->all());

        return back()->with('success', 'Collection created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Collection $collection)
    {
        if ($collection->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $collection->update($request->all());

        return back()->with('success', 'Collection updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Collection $collection)
    {
        if ($collection->user_id !== Auth::id()) {
            abort(403);
        }

        $collection->delete();

        return back()->with('success', 'Collection deleted successfully.');
    }

    /**
     * Add a game to the collection.
     */
    public function addGame(Request $request, Collection $collection)
    {
        if ($collection->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'game_id' => 'required|exists:games,id',
        ]);

        $game = Game::findOrFail($request->game_id);

        // Ensure the game belongs to the user
        if ($game->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$collection->games()->where('game_id', $game->id)->exists()) {
            $collection->games()->attach($game->id);
            return back()->with('success', 'Game added to collection.');
        }

        return back()->with('info', 'Game is already in this collection.');
    }

    /**
     * Remove a game from the collection.
     */
    public function removeGame(Collection $collection, Game $game)
    {
        if ($collection->user_id !== Auth::id()) {
            abort(403);
        }

        $collection->games()->detach($game->id);

        return back()->with('success', 'Game removed from collection.');
    }
}
