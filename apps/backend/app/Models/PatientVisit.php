<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PatientVisit extends Model
{
    use HasFactory, SoftDeletes;

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
        'visit_date' => 'date:Y-m-d',
        'next_follow_up_date' => 'date:Y-m-d',
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

    public function caseRubrics(): HasMany
    {
        return $this->hasMany(CaseRubric::class);
    }

    public function repertorizationRuns(): HasMany
    {
        return $this->hasMany(RepertorizationRun::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(PatientPrescription::class);
    }

    public function fee(): HasOne
    {
        return $this->hasOne(PatientFee::class);
    }
}
