<?php

namespace App\Services\WhatsApp;

use App\Models\ClinicAppointment;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\PatientPrescription;

class WhatsAppMessageTemplateService
{
    public function render(string $body, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
        }

        return trim($body);
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '01')) {
            return '88'.$digits;
        }

        if (str_starts_with($digits, '8801')) {
            return $digits;
        }

        return $digits;
    }

    public function whatsappUrl(?string $phone, string $message): ?string
    {
        if (! $phone) {
            return null;
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    public function variablesFor(
        ?Patient $patient,
        ?ClinicAppointment $appointment,
        int $doctorId,
        array $overrides = []
    ): array {
        $appointment?->loadMissing(['patient', 'prescription']);
        $patient = $patient ?? $appointment?->patient;
        $prescription = $appointment?->prescription
            ?? ($patient ? $this->latestPrescription($patient) : null);
        $clinic = ClinicSetting::query()->where('doctor_id', $doctorId)->first();

        $appointmentStart = $appointment?->scheduled_start_at;

        return array_merge([
            'patient_name' => $patient?->name ?? '',
            'patient_phone' => $patient?->phone ?? '',
            'appointment_date' => $appointmentStart?->format('Y-m-d') ?? '',
            'appointment_time' => $appointmentStart?->format('h:i A') ?? '',
            'clinic_name' => $clinic?->clinic_name ?? 'Similia AI Clinic',
            'clinic_phone' => $clinic?->phone ?? '',
            'doctor_name' => $clinic?->doctor_display_name ?? $patient?->doctor?->name ?? '',
            'medicine_instruction' => $prescription?->dose_instruction ?? '',
            'remedy_name' => $prescription?->remedy_name ?? '',
            'potency' => $prescription?->potency ?? '',
            'repetition' => $prescription?->repetition ?? '',
            'follow_up_date' => $prescription?->follow_up_date?->toDateString() ?? '',
        ], $overrides);
    }

    private function latestPrescription(Patient $patient): ?PatientPrescription
    {
        return PatientPrescription::query()
            ->where('patient_id', $patient->id)
            ->latest()
            ->first();
    }
}
