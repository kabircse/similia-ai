<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doctor_id',
        'name',
        'age_years',
        'gender',
        'phone',
        'address',
        'occupation',
        'marital_status',
        'emergency_contact',
        'notes',
    ];

    protected $casts = [
        'age_years' => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(PatientVisit::class);
    }

    public function portalInvitations(): HasMany
    {
        return $this->hasMany(PatientPortalInvitation::class);
    }

    public function followUpSubmissions(): HasMany
    {
        return $this->hasMany(PatientFollowUpSubmission::class);
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
