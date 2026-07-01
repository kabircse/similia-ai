<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicAppointmentReminder extends Model
{
    protected $fillable = [
        'clinic_appointment_id',
        'doctor_id',
        'patient_id',
        'reminder_type',
        'channel',
        'status',
        'minutes_before',
        'due_at',
        'sent_at',
        'title',
        'message',
        'metadata',
    ];

    protected $casts = [
        'minutes_before' => 'integer',
        'due_at' => 'datetime',
        'sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ClinicAppointment::class, 'clinic_appointment_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
