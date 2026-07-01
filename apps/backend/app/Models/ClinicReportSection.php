<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicReportSection extends Model
{
    protected $fillable = [
        'clinic_report_run_id',
        'section_key',
        'category',
        'sort_order',
        'title',
        'content',
        'metrics',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'metrics' => 'array',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ClinicReportRun::class, 'clinic_report_run_id');
    }
}
