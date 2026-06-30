<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowUpAnalysisRun extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'previous_visit_id',
        'doctor_id',
        'prescription_id',
        'status',
        'response_level',
        'progress_score',
        'previous_case_snapshot',
        'current_case_snapshot',
        'prescription_snapshot',
        'analysis_summary',
        'remedy_response_assessment',
        'improvement_points',
        'worsening_points',
        'unchanged_points',
        'new_symptoms',
        'old_symptoms_returned',
        'possible_aggravation_signs',
        'red_flags',
        'suggested_follow_up_questions',
        'doctor_review_points',
        'recommended_next_steps',
        'safety_note',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'progress_score' => 'decimal:2',
        'previous_case_snapshot' => 'array',
        'current_case_snapshot' => 'array',
        'prescription_snapshot' => 'array',
        'improvement_points' => 'array',
        'worsening_points' => 'array',
        'unchanged_points' => 'array',
        'new_symptoms' => 'array',
        'old_symptoms_returned' => 'array',
        'possible_aggravation_signs' => 'array',
        'red_flags' => 'array',
        'suggested_follow_up_questions' => 'array',
        'doctor_review_points' => 'array',
        'recommended_next_steps' => 'array',
        'metadata' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'patient_visit_id');
    }

    public function previousVisit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'previous_visit_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(PatientPrescription::class);
    }

    public function progressItems(): HasMany
    {
        return $this->hasMany(FollowUpProgressItem::class);
    }
}
