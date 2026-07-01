<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PatientVisit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'visit_date',
        'visit_type',
        'status',
        'case_source',
        'chief_complaint',
        'raw_case_text',
        'case_sections',
        'missing_questions',
        'red_flags',
        'doctor_notes',
        'next_follow_up_date',
    ];

    protected $casts = [
        'visit_date' => 'date:Y-m-d',
        'next_follow_up_date' => 'date:Y-m-d',
        'case_sections' => 'array',
        'missing_questions' => 'array',
        'red_flags' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function caseRubrics(): HasMany
    {
        return $this->hasMany(CaseRubric::class);
    }

    public function repertorizationRuns(): HasMany
    {
        return $this->hasMany(RepertorizationRun::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(PatientPrescription::class);
    }

    public function fee(): HasOne
    {
        return $this->hasOne(PatientFee::class);
    }

    public function voiceTranscripts(): HasMany
    {
        return $this->hasMany(VoiceTranscript::class);
    }

    public function questionSessions(): HasMany
    {
        return $this->hasMany(CaseQuestionSession::class);
    }

    public function followUpAnalysisRuns(): HasMany
    {
        return $this->hasMany(FollowUpAnalysisRun::class);
    }

    public function potencyGuidanceRuns(): HasMany
    {
        return $this->hasMany(PotencyGuidanceRun::class);
    }

    public function remedyRelationshipRuns(): HasMany
    {
        return $this->hasMany(RemedyRelationshipRun::class);
    }

    public function prescriptionReviewRuns(): HasMany
    {
        return $this->hasMany(PrescriptionReviewRun::class);
    }

    public function patientHandoutRuns(): HasMany
    {
        return $this->hasMany(PatientHandoutRun::class);
    }

    public function portalInvitations(): HasMany
    {
        return $this->hasMany(PatientPortalInvitation::class);
    }

    public function portalFollowUpSubmissions(): HasMany
    {
        return $this->hasMany(PatientFollowUpSubmission::class, 'source_patient_visit_id');
    }

    public function reviewQueueItems(): HasMany
    {
        return $this->hasMany(DoctorReviewQueueItem::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ClinicAppointment::class);
    }
}
