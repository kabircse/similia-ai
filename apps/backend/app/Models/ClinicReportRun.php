<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicReportRun extends Model
{
    protected $fillable = [
        'created_by_id',
        'scope_doctor_id',
        'report_type',
        'status',
        'response_language',
        'resolved_language',
        'period_start',
        'period_end',
        'title',
        'executive_summary',
        'clinical_activity_summary',
        'outcome_summary',
        'remedy_summary',
        'safety_summary',
        'finance_summary',
        'follow_up_summary',
        'key_metrics',
        'dashboard_snapshot',
        'recommendations',
        'limitations',
        'safety_note',
        'error_message',
        'exported_at',
        'printed_at',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'key_metrics' => 'array',
        'dashboard_snapshot' => 'array',
        'recommendations' => 'array',
        'limitations' => 'array',
        'exported_at' => 'datetime',
        'printed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scope_doctor_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ClinicReportSection::class);
    }
}
