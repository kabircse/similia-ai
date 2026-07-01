<?php

namespace App\Services\PatientPortal;

use App\Models\Patient;
use App\Models\PatientFollowUpSubmission;
use App\Models\PatientPortalInvitation;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Support\AiResponseLanguages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatientPortalService
{
    public function createInvitation(
        Patient $patient,
        ?PatientVisit $visit,
        int $doctorId,
        ?int $prescriptionId = null,
        string $purpose = 'follow_up_form',
        int $expiresInDays = 7,
        int $maxSubmissions = 1,
        string $responseLanguage = 'auto',
        ?string $messageToPatient = null
    ): PatientPortalInvitation {
        $prescription = null;

        if ($prescriptionId) {
            $prescription = PatientPrescription::query()
                ->where('patient_id', $patient->id)
                ->where('id', $prescriptionId)
                ->firstOrFail();
        }

        if ($visit) {
            abort_unless($visit->patient_id === $patient->id, 404);
        }

        $secret = Str::random(48);
        $language = AiResponseLanguages::normalize($responseLanguage);

        $invitation = PatientPortalInvitation::create([
            'public_id' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit?->id,
            'doctor_id' => $doctorId,
            'prescription_id' => $prescription?->id,
            'purpose' => $purpose,
            'status' => 'active',
            'response_language' => $language,
            'resolved_language' => AiResponseLanguages::resolved($language),
            'secret_hash' => $this->hashSecret($secret),
            'secret_encrypted' => Crypt::encryptString($secret),
            'token_prefix' => substr($secret, 0, 10),
            'max_submissions' => $maxSubmissions,
            'submission_count' => 0,
            'opened_count' => 0,
            'message_to_patient' => $messageToPatient,
            'expires_at' => now()->addDays($expiresInDays),
            'metadata' => [
                'prescription' => $prescription ? [
                    'remedy_name' => $prescription->remedy_name,
                    'potency' => $prescription->potency,
                    'repetition' => $prescription->repetition,
                    'follow_up_date' => $prescription->follow_up_date?->toDateString(),
                ] : null,
            ],
        ]);

        return $invitation->fresh(['patient', 'visit', 'prescription']);
    }

    public function findUsableInvitation(string $publicId, string $secret): PatientPortalInvitation
    {
        $invitation = PatientPortalInvitation::query()
            ->with(['patient', 'visit', 'prescription'])
            ->where('public_id', $publicId)
            ->first();

        abort_unless($invitation, 404);
        abort_unless(
            hash_equals($invitation->secret_hash, $this->hashSecret($secret)),
            404
        );

        if ($invitation->isExpired()) {
            $invitation->update(['status' => 'expired']);
            abort(410, 'This patient portal link has expired.');
        }

        if ($invitation->status === 'revoked') {
            abort(410, 'This patient portal link has been revoked.');
        }

        if ($invitation->submission_count >= $invitation->max_submissions) {
            abort(409, 'This patient portal link has already been used.');
        }

        return $invitation;
    }

    public function markOpened(PatientPortalInvitation $invitation): PatientPortalInvitation
    {
        $invitation->update([
            'status' => $invitation->status === 'active' ? 'opened' : $invitation->status,
            'opened_at' => $invitation->opened_at ?? now(),
            'opened_count' => $invitation->opened_count + 1,
        ]);

        return $invitation->fresh(['patient', 'visit', 'prescription']);
    }

    public function submitFollowUp(
        PatientPortalInvitation $invitation,
        array $input,
        Request $request
    ): PatientFollowUpSubmission {
        return DB::transaction(function () use ($invitation, $input, $request) {
            $detectedRedFlags = $this->detectRedFlags($input);

            $submission = PatientFollowUpSubmission::create([
                'patient_portal_invitation_id' => $invitation->id,
                'patient_id' => $invitation->patient_id,
                'source_patient_visit_id' => $invitation->patient_visit_id,
                'doctor_id' => $invitation->doctor_id,
                'status' => 'new',
                'response_language' => $invitation->response_language,
                'resolved_language' => $invitation->resolved_language,
                'overall_change' => $input['overall_change'],
                'medicine_taken' => $input['medicine_taken'] ?? null,
                'main_changes' => $input['main_changes'] ?? null,
                'current_symptoms' => $input['current_symptoms'] ?? null,
                'new_symptoms' => $input['new_symptoms'] ?? null,
                'aggravation_notes' => $input['aggravation_notes'] ?? null,
                'other_medicines' => $input['other_medicines'] ?? null,
                'general_notes' => $input['general_notes'] ?? null,
                'red_flag_notes' => $input['red_flag_notes'] ?? null,
                'patient_questions' => $input['patient_questions'] ?? null,
                'general_energy' => $input['general_energy'] ?? null,
                'sleep' => $input['sleep'] ?? null,
                'appetite' => $input['appetite'] ?? null,
                'mood' => $input['mood'] ?? null,
                'preferred_contact_time' => $input['preferred_contact_time'] ?? null,
                'answers' => $input,
                'detected_red_flags' => $detectedRedFlags,
                'submitted_at' => now(),
                'ip_hash' => $this->hashSensitive($request->ip()),
                'user_agent_hash' => $this->hashSensitive($request->userAgent()),
                'metadata' => [
                    'source' => 'patient_portal',
                    'has_detected_red_flags' => count($detectedRedFlags) > 0,
                ],
            ]);

            $nextSubmissionCount = $invitation->submission_count + 1;

            $invitation->update([
                'submission_count' => $nextSubmissionCount,
                'status' => $nextSubmissionCount >= $invitation->max_submissions
                    ? 'submitted'
                    : 'opened',
                'submitted_at' => now(),
            ]);

            return $submission->fresh(['patient', 'sourceVisit', 'convertedVisit']);
        });
    }

    public function revoke(PatientPortalInvitation $invitation): PatientPortalInvitation
    {
        $invitation->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return $invitation->fresh(['patient', 'visit', 'prescription']);
    }

    public function convertToVisit(PatientFollowUpSubmission $submission): PatientVisit
    {
        abort_if($submission->converted_patient_visit_id, 409, 'Submission is already converted.');

        return DB::transaction(function () use ($submission) {
            $visit = PatientVisit::create([
                'patient_id' => $submission->patient_id,
                'doctor_id' => $submission->doctor_id,
                'visit_date' => now()->toDateString(),
                'visit_type' => 'follow_up',
                'status' => 'draft',
                'case_source' => 'patient_portal',
                'chief_complaint' => 'Patient portal follow-up submission',
                'raw_case_text' => $this->formatSubmissionAsCaseText($submission),
                'case_sections' => [
                    'generals' => implode(PHP_EOL, array_filter([
                        'Overall change: '.$this->valueOrDash($submission->overall_change),
                        'Energy: '.$this->valueOrDash($submission->general_energy),
                        'Sleep: '.$this->valueOrDash($submission->sleep),
                        'Appetite: '.$this->valueOrDash($submission->appetite),
                        'Mood: '.$this->valueOrDash($submission->mood),
                    ])),
                    'current_medicine' => $this->valueOrDash($submission->other_medicines),
                    'reports_note' => $this->formatSubmissionAsCaseText($submission),
                ],
                'missing_questions' => [],
                'red_flags' => $submission->detected_red_flags ?? [],
                'doctor_notes' => "Created from patient portal follow-up submission #{$submission->id}.",
                'next_follow_up_date' => null,
            ]);

            $submission->update([
                'status' => 'converted_to_visit',
                'converted_patient_visit_id' => $visit->id,
                'converted_at' => now(),
            ]);

            return $visit;
        });
    }

    private function formatSubmissionAsCaseText(PatientFollowUpSubmission $submission): string
    {
        $lines = [
            'Patient portal follow-up submission',
            '',
            'Overall change: '.$this->valueOrDash($submission->overall_change),
            'Medicine taken: '.($submission->medicine_taken === null
                ? '-'
                : ($submission->medicine_taken ? 'Yes' : 'No')),
            'Main changes: '.$this->valueOrDash($submission->main_changes),
            'Current symptoms: '.$this->valueOrDash($submission->current_symptoms),
            'New symptoms: '.$this->valueOrDash($submission->new_symptoms),
            'Aggravation/reaction: '.$this->valueOrDash($submission->aggravation_notes),
            'Energy: '.$this->valueOrDash($submission->general_energy),
            'Sleep: '.$this->valueOrDash($submission->sleep),
            'Appetite: '.$this->valueOrDash($submission->appetite),
            'Mood: '.$this->valueOrDash($submission->mood),
            'Other medicines: '.$this->valueOrDash($submission->other_medicines),
            'General notes: '.$this->valueOrDash($submission->general_notes),
            'Warning concerns: '.$this->valueOrDash($submission->red_flag_notes),
            'Patient questions: '.$this->valueOrDash($submission->patient_questions),
            'Preferred contact time: '.$this->valueOrDash($submission->preferred_contact_time),
        ];

        if (! empty($submission->detected_red_flags)) {
            $lines[] = 'Detected red flags: '.implode(', ', $submission->detected_red_flags);
        }

        return implode(PHP_EOL, $lines);
    }

    private function detectRedFlags(array $input): array
    {
        $haystack = strtolower(implode(' ', array_filter([
            $input['current_symptoms'] ?? null,
            $input['new_symptoms'] ?? null,
            $input['aggravation_notes'] ?? null,
            $input['red_flag_notes'] ?? null,
            $input['general_notes'] ?? null,
        ])));

        $rules = [
            'Breathing difficulty' => ['breathing difficulty', 'shortness of breath', 'cannot breathe', 'breathless'],
            'Chest pain' => ['chest pain', 'pressure in chest'],
            'Unconsciousness' => ['unconscious', 'fainted', 'fainting', 'loss of consciousness'],
            'Unusual bleeding' => ['unusual bleeding', 'heavy bleeding', 'blood loss'],
            'Self-harm concern' => ['self harm', 'self-harm', 'suicide', 'suicidal'],
            'Rapid worsening' => ['rapid worsening', 'sudden worsening', 'getting worse quickly'],
        ];

        $detected = [];

        foreach ($rules as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    $detected[] = $label;
                    break;
                }
            }
        }

        if (trim((string) ($input['red_flag_notes'] ?? '')) !== '') {
            $detected[] = 'Patient reported warning concern';
        }

        return array_values(array_unique($detected));
    }

    private function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private function hashSensitive(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash('sha256', $value.'|'.config('app.key'));
    }

    private function valueOrDash(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }
}
