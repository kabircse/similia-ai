<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDoctorReviewQueueItemRequest;
use App\Http\Resources\DoctorReviewQueueItemResource;
use App\Models\DoctorReviewQueueItem;
use App\Services\Audit\AuditLogger;
use App\Services\ReviewQueue\DoctorReviewQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorReviewQueueController extends Controller
{
    public function summary(
        Request $request,
        DoctorReviewQueueService $service
    ): JsonResponse {
        $user = $request->user();

        return response()->json([
            'data' => $service->summaryForDoctor($user->id, $user->role),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $items = DoctorReviewQueueItem::query()
            ->with([
                'patient:id,name,phone',
                'visit:id,visit_date,chief_complaint',
                'followUpSubmission.patient',
                'followUpSubmission.sourceVisit',
                'followUpSubmission.convertedVisit',
            ])
            ->when($user->role !== 'admin', fn ($query) => $query->where('doctor_id', $user->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', (string) $request->string('priority')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', (string) $request->string('category')))
            ->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'normal' THEN 3
                    ELSE 4
                END
            ")
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return DoctorReviewQueueItemResource::collection($items);
    }

    public function show(
        Request $request,
        DoctorReviewQueueItem $queueItem
    ): DoctorReviewQueueItemResource {
        $this->ensureCanAccessItem($request, $queueItem);

        return new DoctorReviewQueueItemResource(
            $queueItem->load([
                'patient',
                'visit',
                'followUpSubmission.patient',
                'followUpSubmission.sourceVisit',
                'followUpSubmission.convertedVisit',
            ])
        );
    }

    public function updateStatus(
        UpdateDoctorReviewQueueItemRequest $request,
        DoctorReviewQueueItem $queueItem,
        DoctorReviewQueueService $service,
        AuditLogger $auditLogger
    ): DoctorReviewQueueItemResource {
        $this->ensureCanAccessItem($request, $queueItem);

        $validated = $request->validated();
        $item = $service->updateStatus(
            item: $queueItem,
            status: $validated['status'],
            doctorNote: $validated['doctor_note'] ?? null
        );

        $auditLogger->log(
            request: $request,
            category: 'review_queue',
            action: 'updated_doctor_review_queue_item',
            title: 'Doctor review queue item updated',
            description: $item->title,
            patient: $item->patient,
            visit: $item->visit,
            entity: $item,
            metadata: [
                'status' => $item->status,
                'priority' => $item->priority,
                'category' => $item->category,
            ]
        );

        return new DoctorReviewQueueItemResource($item);
    }

    private function ensureCanAccessItem(Request $request, DoctorReviewQueueItem $item): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($item->doctor_id === $user->id, 403);
    }
}
