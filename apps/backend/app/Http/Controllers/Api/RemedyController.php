<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RemedyResource;
use App\Models\Remedy;
use App\Models\RemedyAlias;
use App\Services\Remedies\RemedyNormalizer;
use Illuminate\Http\Request;

class RemedyController extends Controller
{
    public function index(Request $request, RemedyNormalizer $normalizer)
    {
        $search = trim((string) $request->query('q', ''));
        $normalized = $normalizer->normalize($search);

        $remedies = Remedy::query()
            ->with('aliases')
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search, $normalized) {
                $query->where(function ($query) use ($search, $normalized) {
                    $query
                        ->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('abbreviation', 'ILIKE', "%{$search}%")
                        ->orWhere('code', 'ILIKE', "%{$search}%")
                        ->orWhere('normalized_name', 'ILIKE', "%{$normalized}%")
                        ->orWhere('normalized_abbreviation', 'ILIKE', "%{$normalized}%")
                        ->orWhereIn('id', RemedyAlias::query()
                            ->select('remedy_id')
                            ->where('alias', 'ILIKE', "%{$search}%")
                            ->orWhere('normalized_alias', 'ILIKE', "%{$normalized}%"));
                });
            })
            ->orderBy('name')
            ->limit($request->integer('limit', 20))
            ->get();

        return RemedyResource::collection($remedies);
    }

    public function show(Remedy $remedy): RemedyResource
    {
        return new RemedyResource($remedy->load('aliases'));
    }
}
