<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function index()
    {
        // Fetch latest reviews
        $latestReviews = Review::with(['user', 'game'])
            ->where('is_public', true)
            ->latest()
            ->take(20)
            ->get();
            
        // Fetch popular reviews (high rating for now)
        $popularReviews = Review::with(['user', 'game'])
            ->where('is_public', true)
            ->where('rating', '>=', 4)
            ->inRandomOrder()
            ->take(20)
            ->get();

        // Merge and map
        $reviews = $latestReviews->map(function ($review) {
            return $this->formatReview($review, 'new');
        })->merge($popularReviews->map(function ($review) {
            return $this->formatReview($review, 'popular');
        }))->unique('id')->values();

        // Also fetch games the user can review (games they own but haven't reviewed yet?)
        // For simplicity, just list all their games for the dropdown.
        $userGames = Auth::user()->games()->select('id', 'title')->orderBy('title')->get();

        return Inertia::render('Reviews/Index', [
            'reviews' => $reviews,
            'userGames' => $userGames,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'game_id' => 'required|exists:games,id',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:1000',
            'is_public' => 'boolean'
        ]);

        // Verify ownership
        $game = Game::where('id', $validated['game_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Check if already reviewed?
        // Let's allow multiple reviews or update existing?
        // Usually one review per game per user.
        $review = Review::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'game_id' => $game->id,
            ],
            [
                'rating' => $validated['rating'],
                'content' => $validated['content'],
                'is_public' => $validated['is_public'] ?? true,
            ]
        );

        return redirect()->back()->with('success', 'Review posted successfully!');
    }

    private function formatReview($review, $category)
    {
        return [
            'id' => $review->id,
            'user' => $review->user->name,
            'game' => $review->game->title,
            'rating' => $review->rating,
            'content' => $review->content,
            'date' => $review->created_at->diffForHumans(),
            'category' => $category,
            'user_id' => $review->user_id, // To allow editing if needed
        ];
    }
}
