<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseQuestionMessage extends Model
{
    protected $fillable = [
        'case_question_session_id',
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'parent_message_id',
        'role',
        'message_type',
        'status',
        'question_key',
        'category',
        'importance',
        'content',
        'extracted_update',
        'metadata',
        'answered_at',
    ];

    protected $casts = [
        'extracted_update' => 'array',
        'metadata' => 'array',
        'answered_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CaseQuestionSession::class, 'case_question_session_id');
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(CaseQuestionMessage::class, 'parent_message_id');
    }

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
