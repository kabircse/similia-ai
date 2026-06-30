<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KnowledgeChunkResource;
use App\Models\KnowledgeChunk;
use App\Services\Knowledge\SimpleTextEmbedding;
use Illuminate\Http\Request;

class KnowledgeSearchController extends Controller
{
    public function index(Request $request, SimpleTextEmbedding $embedder)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:1000'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'book_code' => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', 'string', 'max:20'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $queryVector = $embedder->toPgVector(
            $embedder->embed($validated['q'])
        );

        $chunks = KnowledgeChunk::query()
            ->with('knowledgeSource')
            ->whereHas('knowledgeSource', function ($query) use ($request): void {
                $query->where('is_active', true)
                    ->where(function ($query) use ($request): void {
                        $query->whereIn('visibility', ['global_demo', 'clinic'])
                            ->orWhere('owner_user_id', $request->user()->id);
                    });
            })
            ->select('knowledge_chunks.*')
            ->selectRaw('embedding <=> ?::vector as distance', [$queryVector])
            ->when($validated['source_type'] ?? null, fn ($query, $type) => $query->where('source_type', $type))
            ->when($validated['book_code'] ?? null, fn ($query, $bookCode) => $query->where('book_code', $bookCode))
            ->when($validated['language'] ?? null, fn ($query, $language) => $query->where('language', $language))
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?::vector', [$queryVector])
            ->limit($validated['limit'] ?? 8)
            ->get();

        return KnowledgeChunkResource::collection($chunks);
    }
}
