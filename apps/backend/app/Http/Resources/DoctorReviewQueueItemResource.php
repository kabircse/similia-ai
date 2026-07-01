<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorReviewQueueItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'patient_follow_up_submission_id' => $this->patient_follow_up_submission_id,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'title' => $this->title,
            'summary' => $this->summary,
            'doctor_note' => $this->doctor_note,
            'action_url' => $this->action_url,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'in_review_at' => $this->in_review_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'dismissed_at' => $this->dismissed_at?->toISOString(),
            'red_flags' => $this->red_flags ?? [],
            'metadata' => $this->metadata ?? [],
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'name' => $this->patient?->name,
                'phone' => $this->patient?->phone,
            ]),
            'visit' => $this->whenLoaded('visit', fn () => [
                'id' => $this->visit?->id,
                'visit_date' => $this->visit?->visit_date?->toDateString(),
                'chief_complaint' => $this->visit?->chief_complaint,
            ]),
            'follow_up_submission' => $this->whenLoaded(
                'followUpSubmission',
                fn () => new PatientFollowUpSubmissionResource($this->followUpSubmission)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
