<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepertoryRubric extends Model
{
    protected $fillable = [
        'source',
        'chapter',
        'rubric_path',
        'rubric_text',
        'parent_id',
        'page',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function remedies(): HasMany
    {
        return $this->hasMany(RepertoryRubricRemedy::class);
    }
}