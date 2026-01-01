<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\EbayService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class EbayServiceTest extends TestCase
{
    public function test_get_price_uses_short_platform_name()
    {
        Config::set('services.ebay.client_id', 'test_id');
        Config::set('services.ebay.client_secret', 'test_secret');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn('fake_token');

        Http::fake([
            'api.ebay.com/*' => Http::response([
                'itemSummaries' => [
                    ['price' => ['value' => '20.00', 'currency' => 'GBP']]
                ]
            ], 200),
        ]);

        $service = new EbayService();
        $price = $service->getPrice('God of War', 'PlayStation 5');

        $this->assertEquals(20.00, $price);

        Http::assertSent(function ($request) {
            // Check if the query parameter q contains 'God of War PS5'
            // Laravel's test request usually has the params available
            return $request['q'] === 'God of War PS5';
        });
    }

    public function test_get_price_falls_back_to_full_name()
    {
        Config::set('services.ebay.client_id', 'test_id');
        Config::set('services.ebay.client_secret', 'test_secret');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn('fake_token');

        Http::fake([
            // First call with PS5 fails (empty results)
            'api.ebay.com/buy/browse/v1/item_summary/search?q=God%20of%20War%20PS5*' => Http::response([
                'itemSummaries' => []
            ], 200),
            // Second call with PlayStation 5 succeeds
            'api.ebay.com/buy/browse/v1/item_summary/search?q=God%20of%20War%20PlayStation%205*' => Http::response([
                'itemSummaries' => [
                    ['price' => ['value' => '25.00', 'currency' => 'GBP']]
                ]
            ], 200),
        ]);

        $service = new EbayService();
        $price = $service->getPrice('God of War', 'PlayStation 5');

        $this->assertEquals(25.00, $price);
    }
}
