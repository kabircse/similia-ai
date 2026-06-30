<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $logs = AuditLog::query()
            ->with(['user', 'patient', 'visit'])
            ->when($user->role !== 'admin', fn ($query) => $query->where('user_id', $user->id))
            ->when($request->query('patient_id'), fn ($query, $patientId) => $query->where('patient_id', $patientId))
            ->when($request->query('patient_visit_id'), fn ($query, $visitId) => $query->where('patient_visit_id', $visitId))
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return AuditLogResource::collection($logs);
    }
}
