<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientHandoutRun extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'prescription_id',
        'prescription_review_run_id',
        'status',
        'handout_type',
        'response_language',
        'resolved_language',
        'title',
        'patient_summary',
        'medicine_instruction',
        'diet_lifestyle_instruction',
        'follow_up_instruction',
        'warning_instruction',
        'case_snapshot',
        'prescription_snapshot',
        'clinic_snapshot',
        'review_snapshot',
        'warning_signs',
        'do_and_dont',
        'metadata',
        'footer_note',
        'safety_note',
        'error_message',
        'reviewed_at',
        'printed_at',
    ];

    protected $casts = [
        'case_snapshot' => 'array',
        'prescription_snapshot' => 'array',
        'clinic_snapshot' => 'array',
        'review_snapshot' => 'array',
        'warning_signs' => 'array',
        'do_and_dont' => 'array',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
        'printed_at' => 'datetime',
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

    public function prescriptionReviewRun(): BelongsTo
    {
        return $this->belongsTo(PrescriptionReviewRun::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PatientHandoutSection::class);
    }
}
