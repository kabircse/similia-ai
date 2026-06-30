<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'knowledge_source_id',
        'owner_user_id',
        'source',
        'external_id',
        'source_type',
        'book_code',
        'section_no',
        'chunk_index',
        'title',
        'summary',
        'content',
        'content_hash',
        'language',
        'source_ref',
        'metadata',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'section_no' => 'integer',
        'chunk_index' => 'integer',
        'metadata' => 'array',
    ];

    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
