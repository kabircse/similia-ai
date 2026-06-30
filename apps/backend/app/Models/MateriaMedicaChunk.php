<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MateriaMedicaChunk extends Model
{
    protected $fillable = [
        'import_key',
        'source',
        'source_title',
        'remedy_id',
        'remedy_code',
        'remedy_name',
        'section',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function remedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class);
    }
}
