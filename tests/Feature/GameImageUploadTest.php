<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_image_when_creating_game()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        
        $file = UploadedFile::fake()->image('game.jpg');

        $response = $this->actingAs($user)->post('/games', [
            'title' => 'Test Game',
            'platform_id' => $platform->id,
            'image_file' => $file,
            'status' => 'uncategorized'
        ]);

        $response->assertRedirect();
        
        $game = Game::first();
        
        $this->assertNotNull($game->image_url);
        $this->assertStringStartsWith('/storage/games/', $game->image_url);
        
        // Assert file exists in storage
        $path = str_replace('/storage/', '', $game->image_url);
        Storage::disk('public')->assertExists($path);
    }

    public function test_user_can_upload_image_when_updating_game()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PC', 'slug' => 'pc']);
        
        $game = Game::create([
            'user_id' => $user->id,
            'title' => 'Old Game',
            'platform_id' => $platform->id,
            'status' => 'uncategorized'
        ]);

        $file = UploadedFile::fake()->image('new-cover.jpg');

        $response = $this->actingAs($user)->put("/games/{$game->id}", [
            'title' => 'Updated Game',
            'platform_id' => $platform->id,
            'image_file' => $file,
            'status' => 'uncategorized'
        ]);

        $response->assertRedirect();
        
        $game->refresh();
        
        $this->assertEquals('Updated Game', $game->title);
        $this->assertStringStartsWith('/storage/games/', $game->image_url);
        
        $path = str_replace('/storage/', '', $game->image_url);
        Storage::disk('public')->assertExists($path);
    }
}
