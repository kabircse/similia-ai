<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientVisit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'visit_date',
        'visit_type',
        'status',
        'case_source',
        'chief_complaint',
        'raw_case_text',
        'case_sections',
        'missing_questions',
        'red_flags',
        'doctor_notes',
        'next_follow_up_date',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'next_follow_up_date' => 'date',
        'case_sections' => 'array',
        'missing_questions' => 'array',
        'red_flags' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}