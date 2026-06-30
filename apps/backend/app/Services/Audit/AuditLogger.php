<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientVisit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(
        Request $request,
        string $category,
        string $action,
        string $title,
        ?string $description = null,
        ?Patient $patient = null,
        ?PatientVisit $visit = null,
        ?Model $entity = null,
        array $metadata = [],
        ?array $before = null,
        ?array $after = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $request->user()?->id,
            'patient_id' => $patient?->id ?? $visit?->patient_id,
            'patient_visit_id' => $visit?->id,
            'category' => $category,
            'action' => $action,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->getKey(),
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function changedFields(Model $model, array $safeFields): array
    {
        $changes = [];

        foreach ($safeFields as $field) {
            if ($model->wasChanged($field)) {
                $changes[$field] = [
                    'old' => $model->getOriginal($field),
                    'new' => $model->{$field},
                ];
            }
        }

        return $changes;
    }
}
