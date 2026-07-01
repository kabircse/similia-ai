<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSource extends Model
{
    protected $fillable = [
        'owner_user_id',
        'source',
        'external_id',
        'code',
        'title',
        'author',
        'source_type',
        'language',
        'edition',
        'source_ref',
        'visibility',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }
}
