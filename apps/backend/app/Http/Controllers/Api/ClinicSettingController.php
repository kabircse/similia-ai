<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClinicSettingRequest;
use App\Http\Resources\ClinicSettingResource;
use App\Models\ClinicSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicSettingController extends Controller
{
    public function show(Request $request, ?User $doctor = null): JsonResponse
    {
        return (new ClinicSettingResource($this->settingFor($request, $doctor)))
            ->response()
            ->setStatusCode(200);
    }

    public function update(
        UpdateClinicSettingRequest $request,
        AuditLogger $auditLogger,
        ?User $doctor = null
    ): JsonResponse {
        $setting = $this->settingFor($request, $doctor);

        $safeFields = [
            'clinic_name',
            'tagline',
            'doctor_display_name',
            'doctor_qualification',
            'phone',
            'email',
            'website',
            'address',
            'logo_url',
            'default_currency',
            'default_consultation_fee',
            'default_followup_fee',
            'medicine_fee_included',
            'prescription_footer',
            'case_sheet_footer',
            'prescription_header',
            'prescription_disclaimer',
            'appointment_default_duration_minutes',
            'appointment_default_timezone',
        ];

        $before = $setting->only($safeFields);

        $setting->update([
            ...$request->validated(),
            'default_currency' => $request->validated('default_currency') ?? 'BDT',
            'default_consultation_fee' => $request->validated('default_consultation_fee') ?? 0,
            'default_followup_fee' => $request->validated('default_followup_fee') ?? 0,
            'medicine_fee_included' => $request->boolean('medicine_fee_included'),
            'prescription_header' => $request->validated('prescription_header'),
            'prescription_disclaimer' => $request->validated('prescription_disclaimer'),
            'appointment_default_duration_minutes' => $request->validated('appointment_default_duration_minutes'),
            'appointment_default_timezone' => $request->validated('appointment_default_timezone'),
        ]);

        $setting = $setting->fresh();

        $auditLogger->log(
            request: $request,
            category: 'clinic_settings',
            action: 'updated',
            title: 'Clinic settings updated',
            description: $setting->clinic_name,
            entity: $setting,
            before: $before,
            after: $setting->only($safeFields)
        );

        return (new ClinicSettingResource($setting))
            ->response()
            ->setStatusCode(200);
    }

    private function settingFor(Request $request, ?User $doctor = null): ClinicSetting
    {
        $actor = $request->user();
        abort_unless($actor, 401);

        if ($doctor && $doctor->id !== $actor->id && $actor->role !== 'admin') {
            abort(403);
        }

        $targetUser = $doctor ?? $actor;

        return ClinicSetting::firstOrCreate(
            [
                'doctor_id' => $targetUser->id,
            ],
            [
                'clinic_name' => 'Similia AI Clinic',
                'tagline' => 'AI Clinical Workspace for Classical Homeopathy',
                'doctor_display_name' => $targetUser->name,
                'doctor_qualification' => null,
                'email' => $targetUser->email,
                'default_currency' => 'BDT',
                'default_consultation_fee' => 3000,
                'default_followup_fee' => 2000,
                'medicine_fee_included' => true,
                'prescription_footer' => 'Please follow the doctor-approved instructions and return for follow-up as advised.',
                'case_sheet_footer' => 'Private clinical document for practitioner use only.',
                'prescription_header' => null,
                'prescription_disclaimer' => null,
                'appointment_default_duration_minutes' => 30,
                'appointment_default_timezone' => 'Asia/Dhaka',
            ]
        );
    }
}
