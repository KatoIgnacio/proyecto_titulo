<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContingencyHistory extends Model
{
    public $timestamps = false;

    protected $table = 'contingency_history';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['event_at' => 'datetime'];
    }

    public function contingency(): BelongsTo
    {
        return $this->belongsTo(Contingency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
