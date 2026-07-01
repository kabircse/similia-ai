<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientHandoutSection extends Model
{
    protected $fillable = [
        'patient_handout_run_id',
        'section_key',
        'category',
        'sort_order',
        'title',
        'content',
        'is_important',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_important' => 'boolean',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PatientHandoutRun::class, 'patient_handout_run_id');
    }
}
