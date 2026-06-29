<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepertorizationResult extends Model
{
    protected $fillable = [
        'repertorization_run_id',
        'remedy_code',
        'remedy_name',
        'total_score',
        'rubric_coverage',
        'essential_coverage',
        'rank',
        'supporting_rubrics',
        'missing_important_rubrics',
        'metrics',
    ];

    protected $casts = [
        'total_score' => 'integer',
        'rubric_coverage' => 'integer',
        'essential_coverage' => 'integer',
        'rank' => 'integer',
        'supporting_rubrics' => 'array',
        'missing_important_rubrics' => 'array',
        'metrics' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(RepertorizationRun::class, 'repertorization_run_id');
    }
}
