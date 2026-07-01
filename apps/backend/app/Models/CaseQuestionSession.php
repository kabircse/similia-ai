<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseQuestionSession extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'status',
        'language',
        'mode',
        'total_questions',
        'answered_questions',
        'case_snapshot',
        'settings',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'case_snapshot' => 'array',
        'settings' => 'array',
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

    public function messages(): HasMany
    {
        return $this->hasMany(CaseQuestionMessage::class);
    }

    public function pendingQuestions(): HasMany
    {
        return $this->messages()
            ->where('role', 'assistant')
            ->where('message_type', 'question')
            ->where('status', 'pending');
    }
}
