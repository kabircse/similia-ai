<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeChunkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'knowledge_source_id' => $this->knowledge_source_id,
            'source_type' => $this->source_type,
            'book_code' => $this->book_code,
            'source_title' => $this->knowledgeSource?->title,
            'author' => $this->knowledgeSource?->author,
            'edition' => $this->knowledgeSource?->edition,
            'section_no' => $this->section_no,
            'chunk_index' => $this->chunk_index,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'language' => $this->language,
            'source_ref' => $this->source_ref,
            'distance' => $this->when(isset($this->distance), fn () => (float) $this->distance),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
