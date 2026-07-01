<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalDashboardRequest;
use App\Http\Resources\ClinicalDashboardResource;
use App\Services\Analytics\ClinicalDashboardService;

class ClinicalDashboardController extends Controller
{
    public function show(
        ClinicalDashboardRequest $request,
        ClinicalDashboardService $service
    ): ClinicalDashboardResource {
        $user = $request->user();

        return new ClinicalDashboardResource(
            $service->build(
                filters: $request->validated(),
                userId: $user->id,
                role: $user->role
            )
        );
    }
}
