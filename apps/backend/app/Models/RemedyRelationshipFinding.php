<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemedyRelationshipFinding extends Model
{
    protected $fillable = [
        'remedy_relationship_run_id',
        'related_remedy_id',
        'related_remedy_code',
        'related_remedy_name',
        'relationship_type',
        'direction',
        'rank',
        'confidence_score',
        'summary',
        'clinical_note',
        'caution',
        'evidence',
        'source_chunks',
        'metadata',
    ];

    protected $casts = [
        'rank' => 'integer',
        'confidence_score' => 'decimal:2',
        'evidence' => 'array',
        'source_chunks' => 'array',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(RemedyRelationshipRun::class, 'remedy_relationship_run_id');
    }

    public function relatedRemedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class, 'related_remedy_id');
    }
}
