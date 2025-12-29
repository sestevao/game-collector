<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = [
        'user_id',
        'platform_id',
        'steam_appid',
        'catalog_game_id',
        'rawg_id',
        'title',
        'price',
        'current_price',
        'purchase_location',
        'purchased',
        'image_url',
        'metascore',
        'rating',
        'released_at',
        'genres',
        'chart_ranking',
        'status',
    ];

    protected $casts = [
        'released_at' => 'date',
    ];

    public function catalogGame()
    {
        return $this->belongsTo(CatalogGame::class);
    }

    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
