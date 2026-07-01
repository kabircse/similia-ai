<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientPrescription extends Model
{
    protected $fillable = [
        'patient_visit_id',
        'patient_id',
        'doctor_id',
        'repertorization_run_id',
        'repertorization_result_id',
        'source_method',
        'remedy_code',
        'remedy_name',
        'potency',
        'repetition',
        'dose_instruction',
        'reason',
        'advice',
        'food_lifestyle_note',
        'follow_up_date',
        'status',
        'finalized_at',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
        'finalized_at' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'patient_visit_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function repertorizationRun(): BelongsTo
    {
        return $this->belongsTo(RepertorizationRun::class);
    }

    public function repertorizationResult(): BelongsTo
    {
        return $this->belongsTo(RepertorizationResult::class);
    }

    public function portalInvitations(): HasMany
    {
        return $this->hasMany(PatientPortalInvitation::class, 'prescription_id');
    }
}