<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_change_per_page_limit()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        
        // Create 30 games
        for ($i = 0; $i < 30; $i++) {
            Game::create([
                'user_id' => $user->id,
                'platform_id' => $platform->id,
                'title' => "Game $i",
                'status' => 'uncategorized'
            ]);
        }

        // Default pagination (24)
        $this->actingAs($user)
            ->get('/games')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Games/Index')
                ->has('games.data', 24)
                ->where('games.total', 30)
            );

        // Custom pagination (48)
        $this->actingAs($user)
            ->get('/games?per_page=48')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Games/Index')
                ->has('games.data', 30)
            );
        
        // All pagination
        $this->actingAs($user)
            ->get('/games?per_page=all')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Games/Index')
                ->has('games.data', 30)
            );
    }
}
