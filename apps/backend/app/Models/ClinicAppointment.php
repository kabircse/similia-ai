<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicAppointment extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'prescription_id',
        'appointment_type',
        'source',
        'status',
        'scheduled_start_at',
        'scheduled_end_at',
        'timezone',
        'title',
        'reason',
        'doctor_note',
        'patient_instruction',
        'contact_method',
        'send_reminders',
        'reminder_minutes_before',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'no_show_at',
        'metadata',
    ];

    protected $casts = [
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'send_reminders' => 'boolean',
        'reminder_minutes_before' => 'array',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'no_show_at' => 'datetime',
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
        return $this->belongsTo(PatientPrescription::class, 'prescription_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ClinicAppointmentReminder::class);
    }
}
