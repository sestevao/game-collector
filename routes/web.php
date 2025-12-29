<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameLookupController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\LinkedAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RankingController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/games/bulk', [GameController::class, 'bulkStore'])->name('games.bulk-store');
    Route::post('/games/import-steam', [GameController::class, 'importSteam'])->name('games.import-steam');
    Route::post('/games/{game}/refresh-price', [GameController::class, 'refreshPrice'])->name('games.refresh-price');
    Route::post('/games/{game}/refresh-metadata', [GameController::class, 'refreshMetadata'])->name('games.refresh-metadata');
    Route::get('/games/statistics', [GameController::class, 'statistics'])->name('games.statistics');

    // Rankings
    Route::get('/games/best-of-year', [RankingController::class, 'bestOfYear'])->name('games.best-of-year');
    Route::get('/games/popular-2024', [RankingController::class, 'popular2024'])->name('games.popular-2024');
    Route::get('/games/top-250', [RankingController::class, 'top250'])->name('games.top-250');
    
    // Discovery
    Route::get('/games/browse', [RankingController::class, 'browse'])->name('games.browse');
    Route::get('/games/platforms', [RankingController::class, 'platforms'])->name('games.platforms');
    Route::get('/games/stores', [RankingController::class, 'stores'])->name('games.stores');
    Route::get('/games/collections', [RankingController::class, 'collections'])->name('games.collections');

    Route::resource('games', GameController::class);
    
    // Game Lookup
    Route::get('/games/lookup', [GameLookupController::class, 'search'])->name('games.lookup');
    
    // Reviews
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');

    // Public Profile
    Route::get('/user/{id}', [ProfileController::class, 'show'])->name('user.show');

    // Linked Accounts
    Route::get('/auth/{provider}', [LinkedAccountController::class, 'redirectToProvider'])->name('auth.link');
    Route::get('/auth/{provider}/callback', [LinkedAccountController::class, 'handleProviderCallback']);
    Route::delete('/auth/{provider}', [LinkedAccountController::class, 'destroy'])->name('auth.unlink');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
