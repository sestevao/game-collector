<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_metadata_handles_api_error_gracefully()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Unknown Game',
            'status' => 'uncategorized'
        ]);

        // Mock RAWG API to return an error (simulating the issue)
        Http::fake([
            'api.rawg.io/api/games*' => Http::response([
                'error' => 'Some API Error'
            ], 200), // Returning 200 but with error body, or maybe RawgService handles failed responses by returning error array
        ]);
        
        // Note: RawgService implementation:
        // if ($response->failed()) return ['error' => ...];
        // So we can also simulate a 500 error
        
        $response = $this->actingAs($user)->post("/games/{$game->id}/refresh-metadata");

        // Should not be 500
        $response->assertStatus(302); // Redirect back
        $response->assertSessionHasErrors(['message']);
    }

    public function test_refresh_metadata_success()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Cyberpunk 2077',
            'status' => 'uncategorized'
        ]);

        Http::fake([
            // Search
            'https://api.rawg.io/api/games*' => Http::sequence()
                ->push([
                    'results' => [
                        [
                            'id' => 41494,
                            'name' => 'Cyberpunk 2077',
                            'slug' => 'cyberpunk-2077'
                        ]
                    ]
                ], 200)
                ->push([
                    'id' => 41494,
                    'name' => 'Cyberpunk 2077',
                    'metacritic' => 86,
                    'released' => '2020-12-10',
                    'background_image' => 'http://example.com/image.jpg',
                    'rating' => 4.5
                ], 200),
        ]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/refresh-metadata");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $game->refresh();
        $this->assertEquals(41494, $game->rawg_id);
        $this->assertEquals(86, $game->metascore);
    }

    public function test_refresh_metadata_updates_incorrect_title()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        
        // Create a game with a slightly wrong or incomplete title
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Witcher 3', // Incomplete title
            'status' => 'uncategorized'
        ]);

        // Mock RAWG API responses
        Http::fake([
            // 1. Search request returns the correct game
            'https://api.rawg.io/api/games?*' => Http::response([
                'results' => [
                    [
                        'id' => 3328,
                        'name' => 'The Witcher 3: Wild Hunt',
                        'slug' => 'the-witcher-3-wild-hunt'
                    ]
                ]
            ], 200),
            
            // 2. Details request returns the full details
            'https://api.rawg.io/api/games/3328?*' => Http::response([
                'id' => 3328,
                'name' => 'The Witcher 3: Wild Hunt', // Correct official title
                'metacritic' => 92,
                'released' => '2015-05-18',
                'background_image' => 'http://example.com/witcher3.jpg',
                'rating' => 4.7
            ], 200),
        ]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/refresh-metadata");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $game->refresh();
        
        // Assert title is updated
        $this->assertEquals('The Witcher 3: Wild Hunt', $game->title);
        $this->assertEquals(92, $game->metascore);
    }
}
