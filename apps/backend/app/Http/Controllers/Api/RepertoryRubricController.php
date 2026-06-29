<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RepertoryRubricResource;
use App\Models\RepertoryRubric;
use Illuminate\Http\Request;

class RepertoryRubricController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $rubrics = RepertoryRubric::query()
            ->withCount('remedies')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('rubric_path', 'ilike', "%{$search}%")
                        ->orWhere('rubric_text', 'ilike', "%{$search}%")
                        ->orWhere('chapter', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('chapter')
            ->orderBy('rubric_path')
            ->paginate($request->integer('per_page', 20));

        return RepertoryRubricResource::collection($rubrics);
    }

    public function show(RepertoryRubric $rubric): RepertoryRubricResource
    {
        return new RepertoryRubricResource(
            $rubric->loadCount('remedies')
        );
    }
}