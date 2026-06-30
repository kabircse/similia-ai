<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemedySuggestionRun extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'repertorization_run_id',
        'method',
        'status',
        'limit',
        'case_snapshot',
        'selected_rubrics_snapshot',
        'retrieved_sources',
        'settings',
        'safety_note',
        'error_message',
    ];

    protected $casts = [
        'case_snapshot' => 'array',
        'selected_rubrics_snapshot' => 'array',
        'retrieved_sources' => 'array',
        'settings' => 'array',
        'limit' => 'integer',
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

    public function repertorizationRun(): BelongsTo
    {
        return $this->belongsTo(RepertorizationRun::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RemedySuggestionItem::class);
    }
}
