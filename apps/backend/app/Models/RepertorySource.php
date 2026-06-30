<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepertorySource extends Model
{
    protected $fillable = [
        'source',
        'external_id',
        'name',
        'abbreviation',
        'author',
        'edition',
        'remedies_count',
        'rubrics_count',
        'metadata',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'remedies_count' => 'integer',
        'rubrics_count' => 'integer',
        'metadata' => 'array',
    ];

    public function rubrics(): HasMany
    {
        return $this->hasMany(RepertoryRubric::class);
    }

    public function rubricRemedies(): HasMany
    {
        return $this->hasMany(RepertoryRubricRemedy::class);
    }
}
