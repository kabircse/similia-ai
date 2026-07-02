<?php

namespace App\Services\PatientHandouts;

use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\PatientHandoutRun;
use App\Models\PatientHandoutSection;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\PrescriptionReviewRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PatientHandoutService
{
    public function generate(
        Patient $patient,
        PatientVisit $visit,
        int $doctorId,
        ?int $prescriptionId = null,
        ?int $prescriptionReviewRunId = null,
        string $handoutType = 'prescription',
        string $responseLanguage = 'auto',
        string $style = 'simple',
        bool $includeClinicBranding = true,
        bool $includeWarningSigns = true,
        bool $includeDoAndDont = true
    ): PatientHandoutRun {
        $prescription = $this->resolvePrescription($patient, $visit, $prescriptionId);

        if (! $prescription) {
            throw new RuntimeException('Save a prescription before generating a patient handout.');
        }

        $review = $this->resolveReview($patient, $visit, $prescription, $prescriptionReviewRunId);
        $caseSnapshot = $this->caseSnapshot($patient, $visit);
        $prescriptionSnapshot = $this->prescriptionSnapshot($prescription);
        $clinicSnapshot = $includeClinicBranding ? $this->clinicSnapshot($doctorId) : [];
        $reviewSnapshot = $review ? $this->reviewSnapshot($review) : [];

        $payload = [
            'case_snapshot' => $caseSnapshot,
            'prescription_snapshot' => $prescriptionSnapshot,
            'clinic_snapshot' => $this->emptyObjectWhenBlank($clinicSnapshot),
            'review_snapshot' => $this->emptyObjectWhenBlank($reviewSnapshot),
            'response_language' => $responseLanguage,
            'style' => $style,
            'include_warning_signs' => $includeWarningSigns,
            'include_do_and_dont' => $includeDoAndDont,
        ];

        $response = Http::timeout(config('services.ai_service.timeout'))
            ->acceptJson()
            ->post(rtrim(config('services.ai_service.url'), '/').'/patient-handout/generate', $payload);

        if ($response->failed()) {
            throw new RuntimeException('AI service failed with status '.$response->status().'.');
        }

        $handout = $response->json('data') ?? $response->json();

        if (! is_array($handout)) {
            throw new RuntimeException('AI service returned an invalid patient handout response.');
        }

        return DB::transaction(function () use (
            $patient,
            $visit,
            $doctorId,
            $prescription,
            $review,
            $handoutType,
            $responseLanguage,
            $style,
            $includeClinicBranding,
            $includeWarningSigns,
            $includeDoAndDont,
            $caseSnapshot,
            $prescriptionSnapshot,
            $clinicSnapshot,
            $reviewSnapshot,
            $handout
        ): PatientHandoutRun {
            $run = PatientHandoutRun::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit->id,
                'doctor_id' => $doctorId,
                'prescription_id' => $prescription->id,
                'prescription_review_run_id' => $review?->id,
                'status' => 'draft',
                'handout_type' => $handoutType,
                'response_language' => $responseLanguage,
                'resolved_language' => $handout['resolved_language'] ?? null,
                'title' => $handout['title'] ?? 'Patient Treatment Instructions',
                'patient_summary' => $handout['patient_summary'] ?? null,
                'medicine_instruction' => $handout['medicine_instruction'] ?? null,
                'diet_lifestyle_instruction' => $handout['diet_lifestyle_instruction'] ?? null,
                'follow_up_instruction' => $handout['follow_up_instruction'] ?? null,
                'warning_instruction' => $handout['warning_instruction'] ?? null,
                'case_snapshot' => $caseSnapshot,
                'prescription_snapshot' => $prescriptionSnapshot,
                'clinic_snapshot' => $clinicSnapshot,
                'review_snapshot' => $reviewSnapshot,
                'warning_signs' => $handout['warning_signs'] ?? [],
                'do_and_dont' => $handout['do_and_dont'] ?? [],
                'footer_note' => $handout['footer_note'] ?? null,
                'safety_note' => $handout['safety_note'] ?? null,
                'metadata' => [
                    'style' => $style,
                    'include_clinic_branding' => $includeClinicBranding,
                    'include_warning_signs' => $includeWarningSigns,
                    'include_do_and_dont' => $includeDoAndDont,
                    'sections_count' => count($handout['sections'] ?? []),
                    'has_prescription_review' => $reviewSnapshot !== [],
                ],
            ]);

            foreach (($handout['sections'] ?? []) as $section) {
                PatientHandoutSection::create([
                    'patient_handout_run_id' => $run->id,
                    'section_key' => $section['section_key'] ?? 'instruction',
                    'category' => $section['category'] ?? 'instruction',
                    'sort_order' => $section['sort_order'] ?? 1,
                    'title' => $section['title'] ?? 'Instruction',
                    'content' => $section['content'] ?? '',
                    'is_important' => $section['is_important'] ?? false,
                    'metadata' => $section['metadata'] ?? [],
                ]);
            }

            return $run->load(['sections' => fn ($query) => $query->orderBy('sort_order')]);
        });
    }

    public function markPrinted(PatientHandoutRun $run): PatientHandoutRun
    {
        $run->update([
            'status' => 'printed',
            'reviewed_at' => $run->reviewed_at ?? now(),
            'printed_at' => now(),
        ]);

        return $run->fresh()->load(['sections' => fn ($query) => $query->orderBy('sort_order')]);
    }

    private function resolvePrescription(
        Patient $patient,
        PatientVisit $visit,
        ?int $prescriptionId = null
    ): ?PatientPrescription {
        if ($prescriptionId) {
            return PatientPrescription::query()
                ->where('patient_id', $patient->id)
                ->where('patient_visit_id', $visit->id)
                ->where('id', $prescriptionId)
                ->first();
        }

        return PatientPrescription::query()
            ->where('patient_id', $patient->id)
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->first();
    }

    private function resolveReview(
        Patient $patient,
        PatientVisit $visit,
        PatientPrescription $prescription,
        ?int $prescriptionReviewRunId = null
    ): ?PrescriptionReviewRun {
        $query = PrescriptionReviewRun::query()
            ->with(['checks' => fn ($query) => $query->orderBy('id')])
            ->where('patient_id', $patient->id)
            ->where('patient_visit_id', $visit->id);

        if ($prescriptionReviewRunId) {
            $review = (clone $query)
                ->where('id', $prescriptionReviewRunId)
                ->first();

            if (! $review) {
                throw new RuntimeException('Selected prescription review was not found for this visit.');
            }

            return $review;
        }

        return $query
            ->where('prescription_id', $prescription->id)
            ->latest()
            ->first();
    }

    private function caseSnapshot(Patient $patient, PatientVisit $visit): array
    {
        return [
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'age_years' => $patient->age_years,
            'gender' => $patient->gender,
            'visit_id' => $visit->id,
            'visit_date' => $visit->visit_date?->toDateString(),
            'visit_type' => $visit->visit_type,
            'chief_complaint' => $visit->chief_complaint,
            'raw_case_text' => $visit->raw_case_text,
            'case_sections' => $visit->case_sections ?? [],
            'red_flags' => $visit->red_flags ?? [],
            'next_follow_up_date' => $visit->next_follow_up_date?->toDateString(),
        ];
    }

    private function prescriptionSnapshot(PatientPrescription $prescription): array
    {
        return [
            'id' => $prescription->id,
            'remedy_name' => $prescription->remedy_name,
            'potency' => $prescription->potency,
            'repetition' => $prescription->repetition,
            'dose_instruction' => $prescription->dose_instruction,
            'advice' => $prescription->advice,
            'food_lifestyle_note' => $prescription->food_lifestyle_note,
            'follow_up_date' => $prescription->follow_up_date?->toDateString(),
            'status' => $prescription->status,
        ];
    }

    private function clinicSnapshot(int $doctorId): array
    {
        $doctor = User::find($doctorId);

        $setting = ClinicSetting::firstOrCreate(
            [
                'doctor_id' => $doctorId,
            ],
            [
                'clinic_name' => 'Similia AI Clinic',
                'tagline' => 'AI Clinical Workspace for Classical Homeopathy',
                'doctor_display_name' => $doctor?->name,
                'email' => $doctor?->email,
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

        return [
            'clinic_name' => $setting->clinic_name,
            'name' => $setting->clinic_name,
            'tagline' => $setting->tagline,
            'doctor_name' => $setting->doctor_display_name ?: $doctor?->name,
            'doctor_qualification' => $setting->doctor_qualification,
            'phone' => $setting->phone,
            'email' => $setting->email,
            'website' => $setting->website,
            'address' => $setting->address,
            'logo_url' => $setting->logo_url,
            'prescription_footer' => $setting->prescription_footer,
            'prescription_header' => $setting->prescription_header,
            'prescription_disclaimer' => $setting->prescription_disclaimer,
            'appointment_default_duration_minutes' => $setting->appointment_default_duration_minutes,
            'appointment_default_timezone' => $setting->appointment_default_timezone,
        ];
    }

    private function reviewSnapshot(PrescriptionReviewRun $run): array
    {
        return [
            'id' => $run->id,
            'review_status' => $run->review_status,
            'safety_score' => $run->safety_score,
            'review_summary' => $run->review_summary,
            'risk_summary' => $run->risk_summary,
            'red_flags' => $run->red_flags ?? [],
            'missing_information' => $run->missing_information ?? [],
            'recommended_actions' => $run->recommended_actions ?? [],
            'safety_note' => $run->safety_note,
            'checks' => $run->checks->map(fn ($check) => [
                'check_key' => $check->check_key,
                'category' => $check->category,
                'severity' => $check->severity,
                'status' => $check->status,
                'title' => $check->title,
            ])->values()->all(),
        ];
    }

    private function emptyObjectWhenBlank(array $value): array|object
    {
        if ($value === []) {
            return new \stdClass;
        }

        return $value;
    }
}
