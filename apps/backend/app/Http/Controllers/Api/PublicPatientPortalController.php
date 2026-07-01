<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicFollowUpSubmissionRequest;
use App\Http\Resources\PatientFollowUpSubmissionResource;
use App\Services\PatientPortal\PatientPortalService;
use Illuminate\Http\JsonResponse;

class PublicPatientPortalController extends Controller
{
    public function show(
        string $publicId,
        string $secret,
        PatientPortalService $service
    ): JsonResponse {
        $invitation = $service->markOpened(
            $service->findUsableInvitation($publicId, $secret)
        );

        return response()->json([
            'data' => [
                'public_id' => $invitation->public_id,
                'purpose' => $invitation->purpose,
                'status' => $invitation->status,
                'response_language' => $invitation->response_language,
                'resolved_language' => $invitation->resolved_language,
                'message_to_patient' => $invitation->message_to_patient,
                'expires_at' => $invitation->expires_at?->toISOString(),
                'patient' => [
                    'name' => $invitation->patient?->name,
                    'age_years' => $invitation->patient?->age_years,
                    'gender' => $invitation->patient?->gender,
                ],
                'visit' => $invitation->visit ? [
                    'id' => $invitation->visit->id,
                    'visit_date' => $invitation->visit->visit_date?->toDateString(),
                    'chief_complaint' => $invitation->visit->chief_complaint,
                ] : null,
                'prescription' => $invitation->prescription ? [
                    'remedy_name' => $invitation->prescription->remedy_name,
                    'potency' => $invitation->prescription->potency,
                    'repetition' => $invitation->prescription->repetition,
                    'follow_up_date' => $invitation->prescription->follow_up_date?->toDateString(),
                ] : null,
            ],
        ]);
    }

    public function submit(
        PublicFollowUpSubmissionRequest $request,
        string $publicId,
        string $secret,
        PatientPortalService $service
    ): PatientFollowUpSubmissionResource {
        $invitation = $service->findUsableInvitation($publicId, $secret);
        $submission = $service->submitFollowUp(
            invitation: $invitation,
            input: $request->validated(),
            request: $request
        );

        return new PatientFollowUpSubmissionResource($submission);
    }
}
