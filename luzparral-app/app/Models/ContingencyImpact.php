<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContingencyImpact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'affected_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function contingency(): BelongsTo
    {
        return $this->belongsTo(Contingency::class);
    }

    public function supplyPoint(): BelongsTo
    {
        return $this->belongsTo(SupplyPoint::class);
    }
}
