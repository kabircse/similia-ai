<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceTranscript extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'language',
        'source',
        'status',
        'transcript_text',
        'segments',
        'merged_to_case_text',
        'merge_mode',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'segments' => 'array',
        'merged_to_case_text' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
}
