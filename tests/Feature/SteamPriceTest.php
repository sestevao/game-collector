<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

use App\Services\CexService;

class SteamPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_price_from_steam_search()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Half-Life 2',
            'status' => 'uncategorized'
        ]);

        // Ensure PriceCharting is skipped
        config(['services.pricecharting.key' => null]);

        // Mock CeX Service to avoid real Puppeteer calls
        $this->mock(CexService::class, function ($mock) {
            $mock->shouldReceive('getPrice')->andReturn(null);
        });

        // Mock Steam Search and others
        Http::fake([
            'store.steampowered.com/api/storesearch*' => Http::response([
                'items' => [
                    [
                        'id' => 220,
                        'name' => 'Half-Life 2',
                        'price' => [
                            'currency' => 'GBP',
                            'initial' => 850,
                            'final' => 850
                        ]
                    ]
                ]
            ], 200),
            'store.steampowered.com/api/appdetails*' => Http::response([], 200),
            // Mock other services to ensure no stray requests interfere
            '*' => Http::response([], 200),
        ]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/refresh-price");

        $response->assertRedirect();
        $game->refresh();
        $this->assertEquals(8.50, $game->current_price);
        $this->assertEquals(220, $game->steam_appid); // Should be updated
    }
}
