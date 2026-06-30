<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepertoryRubricRemedy extends Model
{
    protected $fillable = [
        'import_key',
        'repertory_source_id',
        'external_id',
        'external_rubric_id',
        'external_remedy_id',
        'repertory_rubric_id',
        'remedy_id',
        'remedy_code',
        'remedy_name',
        'grade',
        'source',
        'metadata',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'external_rubric_id' => 'integer',
        'external_remedy_id' => 'integer',
        'grade' => 'integer',
        'metadata' => 'array',
    ];

    public function repertorySource(): BelongsTo
    {
        return $this->belongsTo(RepertorySource::class);
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(RepertoryRubric::class, 'repertory_rubric_id');
    }

    public function remedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class);
    }
}
