<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicAppointmentRequest;
use App\Http\Requests\UpdateClinicAppointmentRequest;
use App\Http\Requests\UpdateClinicAppointmentStatusRequest;
use App\Http\Resources\ClinicAppointmentResource;
use App\Models\ClinicAppointment;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Services\Appointments\ClinicAppointmentService;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicAppointmentController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = ClinicAppointment::query()
            ->when($user->role !== 'admin', fn ($query) => $query->where('doctor_id', $user->id));

        return response()->json([
            'data' => [
                'today_count' => (clone $base)
                    ->whereDate('scheduled_start_at', now()->toDateString())
                    ->whereIn('status', ['scheduled', 'confirmed'])
                    ->count(),
                'upcoming_count' => (clone $base)
                    ->where('scheduled_start_at', '>=', now())
                    ->whereIn('status', ['scheduled', 'confirmed'])
                    ->count(),
                'overdue_count' => (clone $base)
                    ->where('scheduled_start_at', '<', now())
                    ->whereIn('status', ['scheduled', 'confirmed'])
                    ->count(),
                'next_appointments' => (clone $base)
                    ->with('patient:id,name,phone')
                    ->where('scheduled_start_at', '>=', now())
                    ->whereIn('status', ['scheduled', 'confirmed'])
                    ->orderBy('scheduled_start_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (ClinicAppointment $appointment) => [
                        'id' => $appointment->id,
                        'patient_id' => $appointment->patient_id,
                        'patient_name' => $appointment->patient?->name,
                        'patient_phone' => $appointment->patient?->phone,
                        'title' => $appointment->title,
                        'status' => $appointment->status,
                        'scheduled_start_at' => $appointment->scheduled_start_at?->toISOString(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $appointments = ClinicAppointment::query()
            ->with([
                'patient:id,name,phone,doctor_id',
                'visit:id,visit_date,chief_complaint',
                'prescription:id,remedy_name,potency,follow_up_date',
                'reminders',
            ])
            ->when($user->role !== 'admin', fn ($query) => $query->where('doctor_id', $user->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('appointment_type'), fn ($query) => $query->where('appointment_type', (string) $request->string('appointment_type')))
            ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->where('scheduled_start_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($query) => $query->where('scheduled_start_at', '<=', $request->date('date_to')->endOfDay()))
            ->orderBy('scheduled_start_at')
            ->paginate($request->integer('per_page', 20));

        return ClinicAppointmentResource::collection($appointments);
    }

    public function visitAppointments(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $appointments = ClinicAppointment::query()
            ->with(['patient:id,name,phone', 'reminders'])
            ->where('patient_id', $patient->id)
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return ClinicAppointmentResource::collection($appointments);
    }

    public function store(
        StoreClinicAppointmentRequest $request,
        ClinicAppointmentService $service,
        AuditLogger $auditLogger
    ): ClinicAppointmentResource {
        $validated = $request->validated();
        $patient = Patient::findOrFail($validated['patient_id']);
        $this->ensureCanAccessPatient($request, $patient);

        $doctorId = $request->user()->role === 'admin'
            ? $patient->doctor_id
            : $request->user()->id;

        $appointment = $service->create($validated, $doctorId);

        $auditLogger->log(
            request: $request,
            category: 'appointment',
            action: 'created_appointment',
            title: 'Appointment scheduled',
            description: $appointment->title,
            patient: $appointment->patient,
            visit: $appointment->visit,
            entity: $appointment,
            metadata: [
                'scheduled_start_at' => $appointment->scheduled_start_at?->toISOString(),
                'appointment_type' => $appointment->appointment_type,
            ]
        );

        return new ClinicAppointmentResource($appointment);
    }

    public function storeForVisit(
        StoreClinicAppointmentRequest $request,
        Patient $patient,
        PatientVisit $visit,
        ClinicAppointmentService $service,
        AuditLogger $auditLogger
    ): ClinicAppointmentResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $validated = array_merge($request->validated(), [
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
        ]);
        $doctorId = $request->user()->role === 'admin'
            ? $visit->doctor_id
            : $request->user()->id;

        $appointment = $service->create($validated, $doctorId);

        $auditLogger->log(
            request: $request,
            category: 'appointment',
            action: 'created_visit_appointment',
            title: 'Visit appointment scheduled',
            description: $appointment->title,
            patient: $patient,
            visit: $visit,
            entity: $appointment,
            metadata: [
                'scheduled_start_at' => $appointment->scheduled_start_at?->toISOString(),
                'appointment_type' => $appointment->appointment_type,
            ]
        );

        return new ClinicAppointmentResource($appointment);
    }

    public function show(Request $request, ClinicAppointment $appointment): ClinicAppointmentResource
    {
        $this->ensureCanAccessAppointment($request, $appointment);

        return new ClinicAppointmentResource(
            $appointment->load(['patient', 'visit', 'prescription', 'reminders'])
        );
    }

    public function update(
        UpdateClinicAppointmentRequest $request,
        ClinicAppointment $appointment,
        ClinicAppointmentService $service,
        AuditLogger $auditLogger
    ): ClinicAppointmentResource {
        $this->ensureCanAccessAppointment($request, $appointment);

        $appointment = $service->update($appointment, $request->validated());

        $auditLogger->log(
            request: $request,
            category: 'appointment',
            action: 'updated_appointment',
            title: 'Appointment updated',
            description: $appointment->title,
            patient: $appointment->patient,
            visit: $appointment->visit,
            entity: $appointment
        );

        return new ClinicAppointmentResource($appointment);
    }

    public function updateStatus(
        UpdateClinicAppointmentStatusRequest $request,
        ClinicAppointment $appointment,
        ClinicAppointmentService $service,
        AuditLogger $auditLogger
    ): ClinicAppointmentResource {
        $this->ensureCanAccessAppointment($request, $appointment);
        $validated = $request->validated();

        $appointment = $service->updateStatus(
            appointment: $appointment,
            status: $validated['status'],
            doctorNote: $validated['doctor_note'] ?? null
        );

        $auditLogger->log(
            request: $request,
            category: 'appointment',
            action: 'updated_appointment_status',
            title: 'Appointment status updated',
            description: $appointment->title,
            patient: $appointment->patient,
            visit: $appointment->visit,
            entity: $appointment,
            metadata: [
                'status' => $appointment->status,
            ]
        );

        return new ClinicAppointmentResource($appointment);
    }

    private function ensureCanAccessPatient(Request $request, Patient $patient): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $user->id, 403);
    }

    private function ensureCanAccessAppointment(Request $request, ClinicAppointment $appointment): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($appointment->doctor_id === $user->id, 403);
    }

    private function ensureCanAccessVisit(Request $request, Patient $patient, PatientVisit $visit): void
    {
        abort_unless($visit->patient_id === $patient->id, 404);

        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $user->id, 403);
        abort_unless($visit->doctor_id === $user->id, 403);
    }
}
