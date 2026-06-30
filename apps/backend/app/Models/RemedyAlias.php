<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemedyAlias extends Model
{
    protected $fillable = [
        'remedy_id',
        'alias',
        'normalized_alias',
        'alias_type',
        'source',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function remedy(): BelongsTo
    {
        return $this->belongsTo(Remedy::class);
    }
}
