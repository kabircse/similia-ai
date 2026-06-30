<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MateriaMedicaChunk extends Model
{
    protected $fillable = [
        'import_key',
        'materia_medica_source_id',
        'external_id',
        'external_mm_id',
        'external_remedy_id',
        'source',
        'source_title',
        'remedy_id',
        'remedy_code',
        'remedy_name',
        'section',
        'chunk_index',
        'content',
        'content_hash',
        'language',
        'metadata',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'external_mm_id' => 'integer',
        'external_remedy_id' => 'integer',
        'chunk_index' => 'integer',
        'metadata' => 'array',
    ];

    public function materiaMedicaSource(): BelongsTo
    {
        return $this->belongsTo(MateriaMedicaSource::class);
    }

    public function remedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class);
    }
}
