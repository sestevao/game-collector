<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogGame extends Model
{
    protected $guarded = [];

    public function games()
    {
        return $this->hasMany(Game::class);
    }
}
