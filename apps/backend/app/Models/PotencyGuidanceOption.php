<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PotencyGuidanceOption extends Model
{
    protected $fillable = [
        'potency_guidance_run_id',
        'potency_range',
        'potency_label',
        'rank',
        'suitability_score',
        'rationale',
        'repetition_note',
        'caution',
        'source_chunks',
        'metadata',
    ];

    protected $casts = [
        'rank' => 'integer',
        'suitability_score' => 'decimal:2',
        'source_chunks' => 'array',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PotencyGuidanceRun::class, 'potency_guidance_run_id');
    }
}
