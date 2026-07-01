<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionReviewRun extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'prescription_id',
        'remedy_id',
        'remedy_code',
        'remedy_name',
        'potency',
        'repetition',
        'status',
        'review_status',
        'safety_score',
        'response_language',
        'case_snapshot',
        'prescription_snapshot',
        'remedy_suggestion_snapshot',
        'potency_guidance_snapshot',
        'relationship_snapshot',
        'follow_up_snapshot',
        'review_summary',
        'decision_guidance',
        'risk_summary',
        'red_flags',
        'missing_information',
        'doctor_review_points',
        'recommended_actions',
        'safety_note',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'safety_score' => 'decimal:2',
        'case_snapshot' => 'array',
        'prescription_snapshot' => 'array',
        'remedy_suggestion_snapshot' => 'array',
        'potency_guidance_snapshot' => 'array',
        'relationship_snapshot' => 'array',
        'follow_up_snapshot' => 'array',
        'red_flags' => 'array',
        'missing_information' => 'array',
        'doctor_review_points' => 'array',
        'recommended_actions' => 'array',
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

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(PatientPrescription::class);
    }

    public function remedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(PrescriptionReviewCheck::class);
    }
}
