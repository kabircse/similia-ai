<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepertoryRubric extends Model
{
    protected $fillable = [
        'import_key',
        'repertory_source_id',
        'external_id',
        'external_repertory_id',
        'source',
        'chapter',
        'rubric_path',
        'rubric_text',
        'medicine_count',
        'default_weight',
        'is_selectable',
        'parent_id',
        'page',
        'metadata',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'external_repertory_id' => 'integer',
        'medicine_count' => 'integer',
        'default_weight' => 'integer',
        'is_selectable' => 'boolean',
        'metadata' => 'array',
    ];

    public function repertorySource(): BelongsTo
    {
        return $this->belongsTo(RepertorySource::class);
    }

    public function remedies(): HasMany
    {
        return $this->hasMany(RepertoryRubricRemedy::class);
    }
}
