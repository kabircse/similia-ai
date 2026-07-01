<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PatientFollowUpSubmission extends Model
{
    protected $fillable = [
        'patient_portal_invitation_id',
        'patient_id',
        'source_patient_visit_id',
        'converted_patient_visit_id',
        'doctor_id',
        'status',
        'response_language',
        'resolved_language',
        'overall_change',
        'medicine_taken',
        'main_changes',
        'current_symptoms',
        'new_symptoms',
        'aggravation_notes',
        'other_medicines',
        'general_notes',
        'red_flag_notes',
        'patient_questions',
        'general_energy',
        'sleep',
        'appetite',
        'mood',
        'preferred_contact_time',
        'answers',
        'detected_red_flags',
        'doctor_note',
        'submitted_at',
        'reviewed_at',
        'converted_at',
        'ip_hash',
        'user_agent_hash',
        'metadata',
    ];

    protected $casts = [
        'medicine_taken' => 'boolean',
        'answers' => 'array',
        'detected_red_flags' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'converted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(PatientPortalInvitation::class, 'patient_portal_invitation_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function sourceVisit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'source_patient_visit_id');
    }

    public function convertedVisit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'converted_patient_visit_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function reviewQueueItem(): HasOne
    {
        return $this->hasOne(
            DoctorReviewQueueItem::class,
            'patient_follow_up_submission_id'
        );
    }
}
