<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MateriaMedicaSource extends Model
{
    protected $fillable = [
        'source',
        'external_id',
        'name',
        'author',
        'abbreviation',
        'edition',
        'remedies_count',
        'language',
        'metadata',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'remedies_count' => 'integer',
        'metadata' => 'array',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(MateriaMedicaChunk::class);
    }
}
