<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's public profile.
     */
    public function show(Request $request, $id): Response
    {
        $user = \App\Models\User::withCount(['followers', 'following', 'games', 'reviews'])->findOrFail($id);
        
        // Check if we are viewing our own profile
        $isOwnProfile = $request->user() && $request->user()->id === $user->id;

        // Fetch data based on tab or pre-fetch if dataset is small
        // For now, let's pre-fetch reasonable amounts or all if small app
        
        $library = $user->games()
            ->where('purchased', true)
            ->with('platform')
            ->orderBy('title')
            ->get();

        $wishlist = $user->games()
            ->where('purchased', false)
            ->with('platform')
            ->orderBy('created_at', 'desc')
            ->get();

        $reviews = $user->reviews()
            ->with('game')
            ->latest()
            ->get();

        $collections = $user->collections()
            ->when(!$isOwnProfile, function ($query) {
                return $query->where('is_public', true);
            })
            ->withCount('games')
            ->latest()
            ->get();
            
        $followers = $user->followers()->get();
        $following = $user->following()->get();

        return Inertia::render('Profile/Show', [
            'profileUser' => $user,
            'isOwnProfile' => $isOwnProfile,
            'stats' => [
                'library_count' => $library->count(),
                'wishlist_count' => $wishlist->count(),
                'reviews_count' => $reviews->count(),
                'collections_count' => $collections->count(),
                'followers_count' => $user->followers_count,
                'following_count' => $user->following_count,
            ],
            'library' => $library,
            'wishlist' => $wishlist,
            'reviews' => $reviews,
            'collections' => $collections,
            'followers' => $followers,
            'following' => $following,
        ]);
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'linkedAccounts' => $request->user()->linkedAccounts,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
