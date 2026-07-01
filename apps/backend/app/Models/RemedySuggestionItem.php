<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemedySuggestionItem extends Model
{
    protected $fillable = [
        'remedy_suggestion_run_id',
        'remedy_id',
        'remedy_code',
        'remedy_name',
        'rank',
        'confidence_score',
        'repertory_score',
        'materia_medica_score',
        'knowledge_score',
        'summary',
        'matching_points',
        'differentiating_points',
        'missing_questions',
        'evidence_matrix',
        'repertory_evidence',
        'materia_medica_evidence',
        'potency_considerations',
        'relationship_notes',
        'medical_safety_notes',
        'source_chunks',
        'metadata',
    ];

    protected $casts = [
        'rank' => 'integer',
        'confidence_score' => 'decimal:2',
        'repertory_score' => 'decimal:2',
        'materia_medica_score' => 'decimal:2',
        'knowledge_score' => 'decimal:2',
        'matching_points' => 'array',
        'differentiating_points' => 'array',
        'missing_questions' => 'array',
        'evidence_matrix' => 'array',
        'repertory_evidence' => 'array',
        'materia_medica_evidence' => 'array',
        'potency_considerations' => 'array',
        'relationship_notes' => 'array',
        'medical_safety_notes' => 'array',
        'source_chunks' => 'array',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(RemedySuggestionRun::class, 'remedy_suggestion_run_id');
    }

    public function remedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class);
    }
}
