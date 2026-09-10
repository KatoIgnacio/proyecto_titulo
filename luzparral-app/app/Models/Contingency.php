<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contingency extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'estimated_restore_at' => 'datetime',
            'restored_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function feeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class);
    }

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'source_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function impacts(): HasMany
    {
        return $this->hasMany(ContingencyImpact::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ContingencyHistory::class)->orderBy('event_at');
    }
}
