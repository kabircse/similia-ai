<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionReviewCheck extends Model
{
    protected $fillable = [
        'prescription_review_run_id',
        'doctor_id',
        'check_key',
        'category',
        'severity',
        'status',
        'is_required',
        'is_blocking',
        'title',
        'description',
        'ai_assessment',
        'doctor_note',
        'doctor_confirmed_at',
        'evidence',
        'metadata',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_blocking' => 'boolean',
        'doctor_confirmed_at' => 'datetime',
        'evidence' => 'array',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PrescriptionReviewRun::class, 'prescription_review_run_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
