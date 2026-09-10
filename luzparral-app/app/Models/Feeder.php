<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feeder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
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
