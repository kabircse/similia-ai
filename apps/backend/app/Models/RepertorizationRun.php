<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepertorizationRun extends Model
{
    protected $fillable = [
        'patient_visit_id',
        'doctor_id',
        'method',
        'total_rubrics',
        'essential_rubrics_count',
        'settings',
        'selected_rubrics_snapshot',
    ];

    protected $casts = [
        'total_rubrics' => 'integer',
        'essential_rubrics_count' => 'integer',
        'settings' => 'array',
        'selected_rubrics_snapshot' => 'array',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'patient_visit_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(RepertorizationResult::class);
    }
}