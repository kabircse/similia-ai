<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseRubric extends Model
{
    protected $fillable = [
        'patient_visit_id',
        'repertory_rubric_id',
        'doctor_id',
        'symptom_type',
        'importance',
        'weight',
        'is_essential',
        'note',
    ];

    protected $casts = [
        'weight' => 'integer',
        'is_essential' => 'boolean',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'patient_visit_id');
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(RepertoryRubric::class, 'repertory_rubric_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}