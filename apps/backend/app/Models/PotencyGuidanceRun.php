<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PotencyGuidanceRun extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'prescription_id',
        'remedy_id',
        'remedy_code',
        'remedy_name',
        'case_phase',
        'status',
        'case_snapshot',
        'prescription_snapshot',
        'follow_up_snapshot',
        'retrieved_sources',
        'settings',
        'vitality_level',
        'sensitivity_level',
        'pathology_depth',
        'guidance_summary',
        'repetition_guidance',
        'wait_and_watch_guidance',
        'aggravation_guidance',
        'cautions',
        'follow_up_questions',
        'doctor_review_points',
        'safety_note',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'case_snapshot' => 'array',
        'prescription_snapshot' => 'array',
        'follow_up_snapshot' => 'array',
        'retrieved_sources' => 'array',
        'settings' => 'array',
        'cautions' => 'array',
        'follow_up_questions' => 'array',
        'doctor_review_points' => 'array',
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

    public function options(): HasMany
    {
        return $this->hasMany(PotencyGuidanceOption::class);
    }
}
