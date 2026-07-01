<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorReviewQueueItem extends Model
{
    protected $fillable = [
        'doctor_id',
        'patient_id',
        'patient_visit_id',
        'patient_follow_up_submission_id',
        'category',
        'priority',
        'status',
        'title',
        'summary',
        'doctor_note',
        'action_url',
        'submitted_at',
        'due_at',
        'in_review_at',
        'completed_at',
        'dismissed_at',
        'red_flags',
        'metadata',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'due_at' => 'datetime',
        'in_review_at' => 'datetime',
        'completed_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'red_flags' => 'array',
        'metadata' => 'array',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'patient_visit_id');
    }

    public function followUpSubmission(): BelongsTo
    {
        return $this->belongsTo(
            PatientFollowUpSubmission::class,
            'patient_follow_up_submission_id'
        );
    }
}
