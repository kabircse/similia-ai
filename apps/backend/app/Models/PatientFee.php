<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientFee extends Model
{
    protected $fillable = [
        'patient_visit_id',
        'patient_id',
        'doctor_id',
        'currency',
        'consultation_fee',
        'medicine_fee',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'payment_method',
        'payment_status',
        'payment_date',
        'note',
    ];

    protected $casts = [
        'consultation_fee' => 'decimal:2',
        'medicine_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'payment_date' => 'date',
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
}
