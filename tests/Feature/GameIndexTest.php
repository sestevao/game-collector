<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GameIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_index_passes_correct_counts()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);

        // Create games with different statuses
        for ($i = 0; $i < 5; $i++) {
            Game::create([
                'user_id' => $user->id,
                'status' => 'uncategorized',
                'platform_id' => $platform->id,
                'title' => "Uncategorized Game $i"
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            Game::create([
                'user_id' => $user->id,
                'status' => 'completed',
                'platform_id' => $platform->id,
                'title' => "Completed Game $i"
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Game::create([
                'user_id' => $user->id,
                'status' => 'currently_playing',
                'platform_id' => $platform->id,
                'title' => "Playing Game $i"
            ]);
        }

        $response = $this->actingAs($user)->get('/games');

        $response->assertStatus(200);
        
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Games/Index')
            ->has('counts', function (Assert $counts) {
                $counts->where('all', 10)
                       ->where('uncategorized', 5)
                       ->where('completed', 3)
                       ->where('currently_playing', 2)
                       ->where('played', 0)
                       ->where('not_played', 0);
            })
        );
    }
}
