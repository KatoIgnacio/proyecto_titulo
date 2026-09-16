<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldReportAttachment extends Model
{
    protected $guarded = [];

    public function fieldReport(): BelongsTo
    {
        return $this->belongsTo(FieldReport::class);
    }
}
