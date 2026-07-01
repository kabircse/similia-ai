<?php

namespace Tests\Feature;

use App\Models\FollowUpAnalysisRun;
use App\Models\Patient;
use App\Models\PatientFee;
use App\Models\PatientHandoutRun;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\PrescriptionReviewRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_clinical_dashboard(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Test Patient',
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now(),
            'visit_type' => 'follow_up',
            'chief_complaint' => 'Follow-up',
        ]);

        $prescription = PatientPrescription::create([
            'patient_visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'remedy_name' => 'Calcarea carbonica',
            'remedy_code' => 'calc',
            'potency' => '200C',
            'repetition' => 'single dose',
            'follow_up_date' => now()->addDays(7),
            'status' => 'final',
        ]);

        PatientFee::create([
            'patient_visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'currency' => 'BDT',
            'consultation_fee' => 3000,
            'medicine_fee' => 0,
            'discount_amount' => 0,
            'total_amount' => 3000,
            'paid_amount' => 3000,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);

        FollowUpAnalysisRun::create([
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'prescription_id' => $prescription->id,
            'status' => 'completed',
            'response_level' => 'improved',
            'progress_score' => 45,
            'analysis_summary' => 'Patient improved.',
            'red_flags' => ['Review new chest pain.'],
        ]);

        PrescriptionReviewRun::create([
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'prescription_id' => $prescription->id,
            'status' => 'completed',
            'review_status' => 'ready',
            'safety_score' => 90,
            'review_summary' => 'Ready.',
        ]);

        PatientHandoutRun::create([
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'prescription_id' => $prescription->id,
            'status' => 'printed',
            'handout_type' => 'prescription',
            'title' => 'Patient Treatment Instructions',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/clinical-dashboard?period=30d')
            ->assertOk()
            ->assertJsonPath('data.kpis.visits', 1)
            ->assertJsonPath('data.kpis.prescriptions', 1)
            ->assertJsonPath('data.kpis.patient_handouts', 1)
            ->assertJsonPath('data.outcomes.response_level_distribution.0.response_level', 'improved')
            ->assertJsonPath('data.safety.red_flag_count', 1)
            ->assertJsonPath('data.finance.paid_amount', 3000);
    }

    public function test_doctor_dashboard_is_scoped_to_own_patients(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $otherDoctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $ownPatient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $otherPatient = Patient::factory()->create([
            'doctor_id' => $otherDoctor->id,
        ]);

        PatientVisit::factory()->create([
            'patient_id' => $ownPatient->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now(),
        ]);

        PatientVisit::factory()->create([
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'visit_date' => now(),
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/clinical-dashboard?period=30d')
            ->assertOk()
            ->assertJsonPath('data.kpis.visits', 1);
    }

    public function test_admin_can_filter_dashboard_by_doctor(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $otherDoctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $otherPatient = Patient::factory()->create([
            'doctor_id' => $otherDoctor->id,
        ]);

        PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now(),
        ]);

        PatientVisit::factory()->create([
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'visit_date' => now(),
        ]);

        $this->actingAs($admin);

        $this->getJson("/api/clinical-dashboard?period=30d&doctor_id={$doctor->id}")
            ->assertOk()
            ->assertJsonPath('data.filters.doctor_id', $doctor->id)
            ->assertJsonPath('data.kpis.visits', 1);
    }
}
