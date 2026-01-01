<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\GamePriceManager;
use Mockery;

class GameLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_lookup_prices()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PlayStation 5', 'slug' => 'playstation-5']);

        // Mock GamePriceManager to avoid external calls
        $mockPriceManager = Mockery::mock(GamePriceManager::class);
        $mockPriceManager->shouldReceive('getMarketPrices')
            ->once()
            ->with('God of War', 'PlayStation 5', 'playstation-5', null, false)
            ->andReturn([
                ['source' => 'PriceCharting', 'price' => 25.00, 'currency' => 'GBP', 'fetched_at' => now()->toIso8601String()],
                ['source' => 'eBay', 'price' => 20.00, 'currency' => 'GBP', 'fetched_at' => now()->toIso8601String()],
            ]);

        $this->app->instance(GamePriceManager::class, $mockPriceManager);

        $response = $this->actingAs($user)
            ->getJson(route('games.lookup-prices', [
                'title' => 'God of War',
                'platform_id' => $platform->id
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'prices' => [
                    '*' => ['source', 'price', 'currency', 'fetched_at']
                ],
                'title',
                'platform'
            ])
            ->assertJsonFragment(['title' => 'God of War'])
            ->assertJsonFragment(['platform' => 'PlayStation 5']);
    }

    public function test_can_force_refresh_prices()
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PlayStation 5', 'slug' => 'playstation-5']);

        $mockPriceManager = Mockery::mock(GamePriceManager::class);
        $mockPriceManager->shouldReceive('getMarketPrices')
            ->once()
            ->with('God of War', 'PlayStation 5', 'playstation-5', null, true)
            ->andReturn([]);

        $this->app->instance(GamePriceManager::class, $mockPriceManager);

        $this->actingAs($user)
            ->getJson(route('games.lookup-prices', [
                'title' => 'God of War',
                'platform_id' => $platform->id,
                'refresh' => true
            ]))
            ->assertStatus(200);
    }

    public function test_lookup_prices_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('games.lookup-prices', []));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_can_lookup_game_details()
    {
        $user = User::factory()->create();

        // Mock Http for RawgService
        \Illuminate\Support\Facades\Http::fake([
            'api.rawg.io/api/games/123*' => \Illuminate\Support\Facades\Http::response([
                'id' => 123,
                'name' => 'Test Game',
                'description_raw' => 'A great game',
                'genres' => [['id' => 1, 'name' => 'Action']]
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson(route('games.lookup-details', ['id' => 123]));

        $response->assertStatus(200)
            ->assertJson([
                'id' => 123,
                'name' => 'Test Game',
                'description_raw' => 'A great game'
            ]);
    }
}
