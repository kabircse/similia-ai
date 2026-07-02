<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicSetting extends Model
{
    protected $fillable = [
        'doctor_id',
        'clinic_name',
        'tagline',
        'doctor_display_name',
        'doctor_qualification',
        'phone',
        'email',
        'website',
        'address',
        'logo_url',
        'default_currency',
        'default_consultation_fee',
        'default_followup_fee',
        'medicine_fee_included',
        'prescription_footer',
        'case_sheet_footer',
        'prescription_header',
        'prescription_disclaimer',
        'appointment_default_duration_minutes',
        'appointment_default_timezone',
        'metadata',
    ];

    protected $casts = [
        'default_consultation_fee' => 'decimal:2',
        'default_followup_fee' => 'decimal:2',
        'medicine_fee_included' => 'boolean',
        'appointment_default_duration_minutes' => 'integer',
        'metadata' => 'array',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
