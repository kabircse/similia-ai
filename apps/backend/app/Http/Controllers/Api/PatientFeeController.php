<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesDoctorOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavePatientFeeRequest;
use App\Http\Resources\PatientFeeResource;
use App\Models\Patient;
use App\Models\PatientFee;
use App\Models\PatientVisit;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientFeeController extends Controller
{
    use ResolvesDoctorOwnership;

    public function show(Request $request, Patient $patient, PatientVisit $visit): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $fee = PatientFee::query()
            ->where('patient_visit_id', $visit->id)
            ->first();

        return response()->json([
            'data' => $fee ? new PatientFeeResource($fee) : null,
        ]);
    }

    public function save(
        SavePatientFeeRequest $request,
        Patient $patient,
        PatientVisit $visit,
        AuditLogger $auditLogger
    ): PatientFeeResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $data = $request->validated();

        $consultationFee = (float) ($data['consultation_fee'] ?? 0);
        $medicineFee = (float) ($data['medicine_fee'] ?? 0);
        $discountAmount = (float) ($data['discount_amount'] ?? 0);
        $paidAmount = (float) ($data['paid_amount'] ?? 0);

        $subtotal = $consultationFee + $medicineFee;
        $totalAmount = max($subtotal - $discountAmount, 0);
        $dueAmount = max($totalAmount - $paidAmount, 0);

        $paymentStatus = match (true) {
            $totalAmount <= 0 && $paidAmount <= 0 => 'paid',
            $paidAmount <= 0 => 'unpaid',
            $dueAmount <= 0 => 'paid',
            default => 'partial',
        };

        $fee = PatientFee::updateOrCreate(
            [
                'patient_visit_id' => $visit->id,
            ],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $this->ownerDoctorIdForVisit($request, $visit),

                'currency' => $data['currency'] ?? 'BDT',

                'consultation_fee' => $consultationFee,
                'medicine_fee' => $medicineFee,
                'discount_amount' => $discountAmount,

                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,

                'payment_method' => $data['payment_method'] ?? null,
                'payment_status' => $paymentStatus,
                'payment_date' => $data['payment_date'] ?? null,

                'note' => $data['note'] ?? null,
            ]
        );

        $fee = $fee->fresh();

        $auditLogger->log(
            request: $request,
            category: 'fee',
            action: 'saved',
            title: 'Fee record saved',
            description: $fee->currency.' '.$fee->total_amount.' / '.$fee->payment_status,
            patient: $patient,
            visit: $visit,
            entity: $fee,
            metadata: [
                'currency' => $fee->currency,
                'total_amount' => $fee->total_amount,
                'paid_amount' => $fee->paid_amount,
                'due_amount' => $fee->due_amount,
                'payment_status' => $fee->payment_status,
                'payment_method' => $fee->payment_method,
            ]
        );

        return new PatientFeeResource($fee);
    }

    public function destroy(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        AuditLogger $auditLogger
    ): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $fee = PatientFee::query()
            ->where('patient_visit_id', $visit->id)
            ->first();

        if ($fee) {
            $auditLogger->log(
                request: $request,
                category: 'fee',
                action: 'deleted',
                title: 'Fee record deleted',
                description: $fee->currency.' '.$fee->total_amount,
                patient: $patient,
                visit: $visit,
                entity: $fee
            );

            $fee->delete();
        }

        return response()->json([
            'message' => 'Fee record deleted successfully.',
        ]);
    }

    private function ensureCanAccessVisit(Request $request, Patient $patient, PatientVisit $visit): void
    {
        $user = $request->user();

        abort_unless($visit->patient_id === $patient->id, 404);

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $user->id, 403);
        abort_unless($visit->doctor_id === $user->id, 403);
    }
}
