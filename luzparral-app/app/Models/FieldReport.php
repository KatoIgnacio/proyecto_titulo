<?php

namespace App\Models;

use App\Enums\FieldReportProgress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldReport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'progress_status' => FieldReportProgress::class,
            'observed_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function contingency(): BelongsTo
    {
        return $this->belongsTo(Contingency::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FieldReportAttachment::class);
    }
}
