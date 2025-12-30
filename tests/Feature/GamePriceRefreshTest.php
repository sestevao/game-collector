<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GamePriceRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_price_returns_success_message()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Test Game',
            'status' => 'uncategorized'
        ]);

        // Disable other services via config or mocking
        config(['services.ebay.client_id' => null]);
        config(['services.pricecharting.key' => null]);
        
        // Mock CexService to avoid running the slow Node script
        $this->mock(\App\Services\CexService::class, function ($mock) {
            $mock->shouldReceive('getPrice')->andReturn(null);
        });

        // Mock CheapShark to return a price
        Http::fake([
            'https://www.cheapshark.com/api/1.0/games*' => Http::response([
                [
                    'gameID' => '123',
                    'steamAppID' => '456',
                    'cheapest' => '10.00',
                    'external' => 'Test Game'
                ]
            ], 200),
            // Fallback for other requests
            '*' => Http::response([], 200),
        ]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/refresh-price");

        // If the bug exists (Undefined array key "message"), this will be a 500 error
        // If fixed, it should be a redirect with success session
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        // Also verify the game was updated
        $game->refresh();
        $this->assertEquals(8.20, $game->current_price); // 10.00 * 0.82
        $this->assertEquals('CheapShark', $game->price_source);
    }
}
