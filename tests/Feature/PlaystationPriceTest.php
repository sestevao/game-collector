<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlaystationPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_price_from_playstation_store()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PlayStation 5', 'slug' => 'ps5']);
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Test Game',
            'purchased' => false
        ]);

        // Mock the PlayStation Store response
        // We simulate the HTML with __NEXT_DATA__ JSON
        $mockJson = json_encode([
            'props' => [
                'pageProps' => [
                    'someKey' => [
                        'results' => [
                            [
                                'name' => 'Test Game PS5',
                                'basePrice' => '£59.99'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $mockHtml = '<html><body><script id="__NEXT_DATA__" type="application/json">' . $mockJson . '</script></body></html>';

        Http::fake([
            'store.playstation.com/*' => Http::response($mockHtml, 200),
        ]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/refresh-price");

        $response->assertRedirect();
        $game->refresh();
        
        $this->assertEquals(59.99, $game->current_price);
    }

    public function test_playstation_price_prioritizes_discounted_price()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PlayStation 4', 'slug' => 'ps4']);
        $game = Game::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'title' => 'Discounted Game',
            'purchased' => false
        ]);

        $mockJson = json_encode([
            'props' => [
                'pageProps' => [
                    'results' => [
                        [
                            'name' => 'Discounted Game',
                            'basePrice' => '£49.99',
                            'discountedPrice' => '£24.99'
                        ]
                    ]
                ]
            ]
        ]);

        $mockHtml = '<html><body><script id="__NEXT_DATA__" type="application/json">' . $mockJson . '</script></body></html>';

        Http::fake([
            'store.playstation.com/*' => Http::response($mockHtml, 200),
        ]);

        $this->actingAs($user)->post("/games/{$game->id}/refresh-price");
        $game->refresh();
        
        $this->assertEquals(24.99, $game->current_price);
    }
}
