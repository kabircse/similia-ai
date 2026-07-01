<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PatientPortalInvitation extends Model
{
    protected $fillable = [
        'public_id',
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'prescription_id',
        'purpose',
        'status',
        'response_language',
        'resolved_language',
        'secret_hash',
        'secret_encrypted',
        'token_prefix',
        'max_submissions',
        'submission_count',
        'opened_count',
        'message_to_patient',
        'expires_at',
        'opened_at',
        'submitted_at',
        'revoked_at',
        'metadata',
    ];

    protected $casts = [
        'max_submissions' => 'integer',
        'submission_count' => 'integer',
        'opened_count' => 'integer',
        'expires_at' => 'datetime',
        'opened_at' => 'datetime',
        'submitted_at' => 'datetime',
        'revoked_at' => 'datetime',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(PatientFollowUpSubmission::class);
    }

    public function portalUrl(): ?string
    {
        if (! $this->secret_encrypted) {
            return null;
        }

        $secret = Crypt::decryptString($this->secret_encrypted);
        $frontendUrl = rtrim(
            config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')),
            '/'
        );

        return "{$frontendUrl}/portal/follow-up/{$this->public_id}/{$secret}";
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }

    public function isUsable(): bool
    {
        if (in_array($this->status, ['expired', 'revoked'], true)) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return $this->submission_count < $this->max_submissions;
    }
}
