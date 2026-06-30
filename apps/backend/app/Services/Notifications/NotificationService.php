<?php

namespace App\Services\Notifications;

use App\Models\AiTask;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    public function create(
        User $user,
        string $title,
        ?string $message = null,
        string $type = 'info',
        string $category = 'system',
        ?Patient $patient = null,
        ?PatientVisit $visit = null,
        ?AiTask $aiTask = null,
        ?string $actionUrl = null,
        array $metadata = []
    ): UserNotification {
        return UserNotification::create([
            'user_id' => $user->id,
            'patient_id' => $patient?->id ?? $visit?->patient_id,
            'patient_visit_id' => $visit?->id,
            'ai_task_id' => $aiTask?->id,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'metadata' => $metadata ?: null,
        ]);
    }
}
