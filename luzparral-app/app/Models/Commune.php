<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function feeders(): HasMany
    {
        return $this->hasMany(Feeder::class);
    }

    public function supplyPoints(): HasMany
    {
        return $this->hasMany(SupplyPoint::class);
    }

    public function contingencies(): HasMany
    {
        return $this->hasMany(Contingency::class);
    }
}
