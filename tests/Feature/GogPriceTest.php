<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GogPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_price_from_gog()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Cyberpunk 2077',
            'status' => 'uncategorized'
        ]);

        // Ensure PriceCharting is skipped
        config(['services.pricecharting.key' => null]);

        Http::fake([
            // Steam Search should return empty/null
            'store.steampowered.com/*' => Http::response([], 200),
            
            // CheapShark should return empty
            'cheapshark.com/*' => Http::response([], 200),

            // GOG should return success
            'catalog.gog.com/*' => Http::response([
                'products' => [
                    [
                        'price' => [
                            'finalMoney' => [
                                'amount' => '24.99',
                                'currency' => 'GBP'
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/refresh-price");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(24.99, $game->fresh()->current_price);
    }
}
