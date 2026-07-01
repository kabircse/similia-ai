<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdvancedSearchRequest;
use App\Http\Resources\AdvancedSearchResultResource;
use App\Services\Audit\AuditLogger;
use App\Services\Search\AdvancedSearchService;

class AdvancedSearchController extends Controller
{
    public function index(
        AdvancedSearchRequest $request,
        AdvancedSearchService $service,
        AuditLogger $auditLogger
    ) {
        $user = $request->user();
        $validated = $request->validated();

        $search = $service->search(
            filters: $validated,
            userId: $user->id,
            role: $user->role
        );

        $auditLogger->log(
            request: $request,
            category: 'search',
            action: 'advanced_search',
            title: 'Advanced search performed',
            description: null,
            metadata: [
                'query_hash' => hash('sha256', $search['query']),
                'query_length' => strlen($search['query']),
                'types' => $search['types'],
                'total' => $search['total'],
            ]
        );

        return AdvancedSearchResultResource::collection(collect($search['results']))
            ->additional([
                'meta' => [
                    'query' => $search['query'],
                    'types' => $search['types'],
                    'total' => $search['total'],
                ],
            ]);
    }
}
