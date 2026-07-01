<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpProgressItem extends Model
{
    protected $fillable = [
        'follow_up_analysis_run_id',
        'patient_id',
        'patient_visit_id',
        'category',
        'symptom',
        'change_status',
        'previous_intensity',
        'current_intensity',
        'change_score',
        'evidence',
        'metadata',
    ];

    protected $casts = [
        'previous_intensity' => 'integer',
        'current_intensity' => 'integer',
        'change_score' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(FollowUpAnalysisRun::class, 'follow_up_analysis_run_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'patient_visit_id');
    }
}
