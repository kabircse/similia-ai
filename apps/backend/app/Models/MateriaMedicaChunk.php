<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MateriaMedicaChunk extends Model
{
    protected $fillable = [
        'source',
        'source_title',
        'remedy_code',
        'remedy_name',
        'section',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
