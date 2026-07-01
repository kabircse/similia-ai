<?php

namespace App\Services\ReviewQueue;

use App\Models\DoctorReviewQueueItem;
use App\Models\PatientFollowUpSubmission;
use App\Services\Notifications\NotificationService;

class DoctorReviewQueueService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function createFromPortalSubmission(PatientFollowUpSubmission $submission): DoctorReviewQueueItem
    {
        $submission->loadMissing(['doctor', 'patient', 'sourceVisit']);

        $redFlags = $submission->detected_red_flags ?? [];
        $hasRedFlags = count($redFlags) > 0;
        $priority = match (true) {
            $hasRedFlags => 'urgent',
            $submission->overall_change === 'worse' => 'high',
            default => 'normal',
        };

        $item = DoctorReviewQueueItem::updateOrCreate(
            [
                'category' => 'portal_submission',
                'patient_follow_up_submission_id' => $submission->id,
            ],
            [
                'doctor_id' => $submission->doctor_id,
                'patient_id' => $submission->patient_id,
                'patient_visit_id' => $submission->source_patient_visit_id,
                'priority' => $priority,
                'status' => 'open',
                'title' => $hasRedFlags
                    ? 'Urgent patient portal follow-up needs review'
                    : 'Patient portal follow-up submitted',
                'summary' => $this->buildSubmissionSummary($submission),
                'action_url' => $this->buildActionUrl($submission),
                'submitted_at' => $submission->submitted_at ?? now(),
                'red_flags' => $redFlags,
                'metadata' => [
                    'overall_change' => $submission->overall_change,
                    'medicine_taken' => $submission->medicine_taken,
                    'patient_name' => $submission->patient?->name,
                    'source' => 'patient_portal',
                ],
            ]
        );

        $this->notifyDoctor($item->fresh(['patient', 'visit']), $submission);

        return $item->fresh(['patient', 'visit', 'followUpSubmission']);
    }

    public function updateStatus(
        DoctorReviewQueueItem $item,
        string $status,
        ?string $doctorNote = null
    ): DoctorReviewQueueItem {
        $payload = [
            'status' => $status,
            'doctor_note' => $doctorNote ?? $item->doctor_note,
        ];

        if ($status === 'open') {
            $payload['in_review_at'] = null;
            $payload['completed_at'] = null;
            $payload['dismissed_at'] = null;
        }

        if ($status === 'in_review') {
            $payload['in_review_at'] = $item->in_review_at ?? now();
            $payload['completed_at'] = null;
            $payload['dismissed_at'] = null;
        }

        if ($status === 'completed') {
            $payload['in_review_at'] = $item->in_review_at ?? now();
            $payload['completed_at'] = now();
            $payload['dismissed_at'] = null;
        }

        if ($status === 'dismissed') {
            $payload['in_review_at'] = $item->in_review_at ?? now();
            $payload['completed_at'] = null;
            $payload['dismissed_at'] = now();
        }

        $item->update($payload);

        return $item->fresh(['patient', 'visit', 'followUpSubmission']);
    }

    public function summaryForDoctor(int $doctorId, string $role): array
    {
        $base = DoctorReviewQueueItem::query()
            ->when($role !== 'admin', fn ($query) => $query->where('doctor_id', $doctorId));

        return [
            'open_count' => (clone $base)->where('status', 'open')->count(),
            'in_review_count' => (clone $base)->where('status', 'in_review')->count(),
            'urgent_count' => (clone $base)
                ->whereIn('status', ['open', 'in_review'])
                ->where('priority', 'urgent')
                ->count(),
            'portal_submission_count' => (clone $base)
                ->whereIn('status', ['open', 'in_review'])
                ->where('category', 'portal_submission')
                ->count(),
            'latest_open' => (clone $base)
                ->with(['patient:id,name,phone'])
                ->whereIn('status', ['open', 'in_review'])
                ->orderByRaw("
                    CASE priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'normal' THEN 3
                        ELSE 4
                    END
                ")
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (DoctorReviewQueueItem $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'priority' => $item->priority,
                    'status' => $item->status,
                    'patient_name' => $item->patient?->name,
                    'action_url' => $item->action_url,
                    'created_at' => $item->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function buildSubmissionSummary(PatientFollowUpSubmission $submission): string
    {
        $parts = [
            'Overall change: '.($submission->overall_change ?: 'not answered'),
        ];

        if ($submission->main_changes) {
            $parts[] = 'Main changes: '.mb_strimwidth($submission->main_changes, 0, 180, '...');
        }

        if ($submission->new_symptoms) {
            $parts[] = 'New symptoms: '.mb_strimwidth($submission->new_symptoms, 0, 180, '...');
        }

        if ($submission->red_flag_notes) {
            $parts[] = 'Warning notes: '.mb_strimwidth($submission->red_flag_notes, 0, 180, '...');
        }

        return implode(PHP_EOL, $parts);
    }

    private function buildActionUrl(PatientFollowUpSubmission $submission): string
    {
        if ($submission->source_patient_visit_id) {
            return "/patients/{$submission->patient_id}/visits/{$submission->source_patient_visit_id}";
        }

        return "/patients/{$submission->patient_id}";
    }

    private function notifyDoctor(
        DoctorReviewQueueItem $item,
        PatientFollowUpSubmission $submission
    ): void {
        if (! $submission->doctor) {
            return;
        }

        $isUrgent = $item->priority === 'urgent';
        $message = $submission->patient?->name
            ? "{$submission->patient->name} submitted a follow-up update."
            : 'A patient submitted a follow-up update.';

        $this->notifications->create(
            user: $submission->doctor,
            title: $isUrgent ? 'Urgent portal submission' : 'New portal submission',
            message: $message,
            type: $isUrgent ? 'warning' : 'info',
            category: 'patient_portal',
            patient: $submission->patient,
            visit: $submission->sourceVisit,
            actionUrl: $item->action_url,
            metadata: [
                'queue_item_id' => $item->id,
                'submission_id' => $submission->id,
                'priority' => $item->priority,
                'red_flags' => $item->red_flags ?? [],
            ]
        );
    }
}
