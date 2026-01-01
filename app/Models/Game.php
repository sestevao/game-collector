<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'platform_id',
        'price',
        'current_price',
        'price_source',
        'purchase_location',
        'purchased',
        'image_url',
        'catalog_game_id',
        'steam_appid',
        'rawg_id',
        'igdb_id',
        'metascore',
        'released_at',
        'genres',
        'rating',
        'status',
        'details',
    ];

    protected $casts = [
        'released_at' => 'date',
        'market_prices' => 'array',
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

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_game')
            ->withTimestamps();
    }
}
