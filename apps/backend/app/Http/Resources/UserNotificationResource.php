<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'ai_task_id' => $this->ai_task_id,
            'type' => $this->type,
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->action_url,
            'metadata' => $this->metadata ?? [],
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
