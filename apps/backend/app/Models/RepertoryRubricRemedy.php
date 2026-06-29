<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepertoryRubricRemedy extends Model
{
    protected $fillable = [
        'repertory_rubric_id',
        'remedy_code',
        'remedy_name',
        'grade',
        'source',
        'metadata',
    ];

    protected $casts = [
        'grade' => 'integer',
        'metadata' => 'array',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(RepertoryRubric::class, 'repertory_rubric_id');
    }
}