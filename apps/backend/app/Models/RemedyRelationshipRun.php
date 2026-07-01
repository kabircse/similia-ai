<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemedyRelationshipRun extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_visit_id',
        'doctor_id',
        'primary_remedy_id',
        'primary_remedy_code',
        'primary_remedy_name',
        'comparison_remedy_id',
        'comparison_remedy_code',
        'comparison_remedy_name',
        'purpose',
        'status',
        'response_language',
        'case_snapshot',
        'prescription_snapshot',
        'follow_up_snapshot',
        'retrieved_sources',
        'settings',
        'relationship_summary',
        'sequence_guidance',
        'antidote_guidance',
        'inimical_warning',
        'complementary_note',
        'cautions',
        'doctor_review_points',
        'suggested_questions',
        'safety_note',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'case_snapshot' => 'array',
        'prescription_snapshot' => 'array',
        'follow_up_snapshot' => 'array',
        'retrieved_sources' => 'array',
        'settings' => 'array',
        'cautions' => 'array',
        'doctor_review_points' => 'array',
        'suggested_questions' => 'array',
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

    public function primaryRemedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class, 'primary_remedy_id');
    }

    public function comparisonRemedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class, 'comparison_remedy_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(RemedyRelationshipFinding::class);
    }
}
