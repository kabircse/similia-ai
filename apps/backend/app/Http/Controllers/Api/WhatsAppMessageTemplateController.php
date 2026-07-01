<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenderWhatsAppMessageRequest;
use App\Http\Resources\WhatsAppMessageTemplateResource;
use App\Models\ClinicAppointment;
use App\Models\Patient;
use App\Models\WhatsAppMessageTemplate;
use App\Services\WhatsApp\WhatsAppMessageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsAppMessageTemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $templates = WhatsAppMessageTemplate::query()
            ->when($user->role !== 'admin', function ($query) use ($user) {
                $query->where(function ($scope) use ($user) {
                    $scope->whereNull('doctor_id')
                        ->orWhere('doctor_id', $user->id);
                });
            })
            ->when($user->role !== 'admin', fn ($query) => $query->where('is_active', true))
            ->when($request->filled('category'), fn ($query) => $query->where('category', (string) $request->string('category')))
            ->when($request->filled('language'), fn ($query) => $query->where('language', (string) $request->string('language')))
            ->orderBy('category')
            ->orderBy('language')
            ->orderBy('title')
            ->get();

        return WhatsAppMessageTemplateResource::collection($templates);
    }

    public function render(
        RenderWhatsAppMessageRequest $request,
        WhatsAppMessageTemplateService $service
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $template = WhatsAppMessageTemplate::findOrFail($validated['template_id']);
        $this->ensureCanUseTemplate($request, $template, mustBeActive: true);

        $appointment = ! empty($validated['appointment_id'])
            ? ClinicAppointment::with(['patient', 'prescription'])->findOrFail($validated['appointment_id'])
            : null;

        if ($appointment) {
            $this->ensureCanAccessAppointment($request, $appointment);
        }

        $patient = ! empty($validated['patient_id'])
            ? Patient::with('doctor')->findOrFail($validated['patient_id'])
            : $appointment?->patient;

        if ($patient) {
            $this->ensureCanAccessPatient($request, $patient);
        }

        if ($appointment && $patient && $appointment->patient_id !== $patient->id) {
            abort(422, 'Appointment does not belong to patient.');
        }

        $doctorId = $appointment?->doctor_id ?? $patient?->doctor_id ?? $user->id;
        $variables = $service->variablesFor(
            patient: $patient,
            appointment: $appointment,
            doctorId: $doctorId,
            overrides: $validated['variables'] ?? []
        );
        $message = $service->render($template->body, $variables);
        $phone = $service->normalizePhone($patient?->phone);

        return response()->json([
            'data' => [
                'message' => $message,
                'phone' => $phone,
                'whatsapp_url' => $service->whatsappUrl($phone, $message),
                'variables' => $variables,
                'template' => new WhatsAppMessageTemplateResource($template),
            ],
        ]);
    }

    public function store(Request $request): WhatsAppMessageTemplateResource
    {
        $validated = $this->validatedTemplateInput($request);

        $template = WhatsAppMessageTemplate::create([
            ...$validated,
            'doctor_id' => $request->user()->id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new WhatsAppMessageTemplateResource($template);
    }

    public function update(
        Request $request,
        WhatsAppMessageTemplate $template
    ): WhatsAppMessageTemplateResource {
        $this->ensureCanManageTemplate($request, $template);

        $template->update($this->validatedTemplateInput($request, partial: true));

        return new WhatsAppMessageTemplateResource($template->fresh());
    }

    private function validatedTemplateInput(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $booleanRule = $partial ? ['sometimes', 'boolean'] : ['nullable', 'boolean'];

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'category' => [$required, 'string', Rule::in(WhatsAppMessageTemplate::CATEGORIES)],
            'language' => [$required, 'string', 'max:10'],
            'body' => [$required, 'string', 'max:10000'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:80'],
            'is_active' => $booleanRule,
        ]);
    }

    private function ensureCanUseTemplate(
        Request $request,
        WhatsAppMessageTemplate $template,
        bool $mustBeActive = false
    ): void {
        if ($mustBeActive) {
            abort_unless($template->is_active, 404);
        }

        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless(
            $template->doctor_id === null || $template->doctor_id === $request->user()->id,
            403
        );
    }

    private function ensureCanManageTemplate(Request $request, WhatsAppMessageTemplate $template): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless($template->doctor_id === $request->user()->id, 403);
    }

    private function ensureCanAccessPatient(Request $request, Patient $patient): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $request->user()->id, 403);
    }

    private function ensureCanAccessAppointment(Request $request, ClinicAppointment $appointment): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless($appointment->doctor_id === $request->user()->id, 403);
    }
}
