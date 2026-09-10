<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatasetMetadata extends Model
{
    public $timestamps = false;

    protected $table = 'dataset_metadata';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'parameters_json' => 'array',
        ];
    }
}
