<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'role' => $this->user?->role,
            ]),

            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'name' => $this->patient->name,
                'phone' => $this->patient->phone,
            ] : null),

            'visit' => $this->whenLoaded('visit', fn () => $this->visit ? [
                'id' => $this->visit->id,
                'visit_date' => $this->visit->visit_date?->toDateString(),
                'visit_type' => $this->visit->visit_type,
            ] : null),

            'user_id' => $this->user_id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,

            'category' => $this->category,
            'action' => $this->action,

            'entity_type' => $this->entity_type ? class_basename($this->entity_type) : null,
            'entity_id' => $this->entity_id,

            'title' => $this->title,
            'description' => $this->description,

            'metadata' => $this->metadata ?? [],
            'before' => $this->before ?? [],
            'after' => $this->after ?? [],

            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
