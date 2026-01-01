<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviews_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reviews.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reviews/Index')
            ->has('reviews')
            ->has('userGames')
        );
    }

    public function test_user_can_create_review()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Test Game',
            'status' => 'played',
        ]);

        $response = $this->actingAs($user)->post(route('reviews.store'), [
            'game_id' => $game->id,
            'rating' => 5,
            'content' => 'Great game!',
            'is_public' => true,
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'game_id' => $game->id,
            'rating' => 5,
            'content' => 'Great game!',
        ]);
    }

    public function test_cannot_review_game_owned_by_other_user()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        $game = Game::create([
            'user_id' => $otherUser->id,
            'platform_id' => $platform->id,
            'title' => 'Other User Game',
        ]);

        $response = $this->actingAs($user)->post(route('reviews.store'), [
            'game_id' => $game->id,
            'rating' => 5,
            'content' => 'Trying to review...',
        ]);

        $response->assertStatus(404); // or 403, depending on logic. My controller uses firstOrFail on a query filtering by user_id, so it will be 404.
    }
}
