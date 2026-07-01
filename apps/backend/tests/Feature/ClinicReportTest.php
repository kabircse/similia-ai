<?php

namespace Tests\Feature;

use App\Models\ClinicReportRun;
use App\Models\ClinicReportSection;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClinicReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_doctor_can_generate_clinic_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 10:00:00'));

        Http::fake([
            '*/clinic-report/monthly-summary' => Http::response([
                'title' => 'Monthly Clinic Report: 2026-06-01 to 2026-06-30',
                'resolved_language' => 'en-US',
                'executive_summary' => 'Clinic report summary.',
                'clinical_activity_summary' => 'Activity summary.',
                'outcome_summary' => 'Outcome summary.',
                'remedy_summary' => 'Remedy summary.',
                'safety_summary' => 'Safety summary.',
                'finance_summary' => 'Finance summary.',
                'follow_up_summary' => 'Follow-up summary.',
                'key_metrics' => [
                    'visits' => 1,
                    'prescriptions' => 1,
                ],
                'recommendations' => [
                    'Review overdue follow-ups.',
                ],
                'limitations' => [
                    'Internal audit only.',
                ],
                'safety_note' => 'Internal audit only.',
                'sections' => [
                    [
                        'section_key' => 'overview',
                        'category' => 'summary',
                        'sort_order' => 1,
                        'title' => 'Executive Summary',
                        'content' => 'Clinic report summary.',
                        'metrics' => [
                            'visits' => 1,
                        ],
                        'metadata' => [],
                    ],
                    [
                        'section_key' => 'safety',
                        'category' => 'safety',
                        'sort_order' => 2,
                        'title' => 'Safety Review',
                        'content' => 'Safety summary.',
                        'metrics' => [],
                        'metadata' => [],
                    ],
                ],
            ], 200),
        ]);

        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'visit_date' => '2026-06-10',
            'visit_type' => 'initial',
        ]);

        PatientPrescription::create([
            'patient_visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'remedy_name' => 'Calcarea carbonica',
            'remedy_code' => 'calc',
            'potency' => '200C',
            'repetition' => 'single dose',
            'status' => 'final',
            'created_at' => '2026-06-10 10:00:00',
            'updated_at' => '2026-06-10 10:00:00',
        ]);

        $this->actingAs($doctor);

        $this->postJson('/api/clinic-reports/generate', [
            'report_type' => 'monthly',
            'period' => 'last_month',
            'response_language' => 'en-US',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Monthly Clinic Report: 2026-06-01 to 2026-06-30')
            ->assertJsonPath('data.sections.0.section_key', 'overview');

        $this->assertDatabaseHas('clinic_report_runs', [
            'created_by_id' => $doctor->id,
            'scope_doctor_id' => $doctor->id,
            'title' => 'Monthly Clinic Report: 2026-06-01 to 2026-06-30',
        ]);

        $this->assertDatabaseHas('clinic_report_sections', [
            'section_key' => 'overview',
            'title' => 'Executive Summary',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'clinic_report',
            'action' => 'generated_clinic_report',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/clinic-report/monthly-summary')
            && $request->data()['period_start'] === '2026-06-01'
            && $request->data()['period_end'] === '2026-06-30'
            && $request->data()['dashboard_snapshot']['kpis']['visits'] === 1
            && $request->data()['response_language'] === 'en-US');
    }

    public function test_doctor_can_list_show_print_and_export_clinic_reports(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $otherDoctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $report = ClinicReportRun::create([
            'created_by_id' => $doctor->id,
            'scope_doctor_id' => $doctor->id,
            'report_type' => 'monthly',
            'status' => 'completed',
            'response_language' => 'en-US',
            'resolved_language' => 'en-US',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'title' => 'Monthly Clinic Report',
            'executive_summary' => 'Summary.',
            'key_metrics' => [
                'visits' => 3,
            ],
            'recommendations' => [
                'Review follow-ups.',
            ],
            'limitations' => [
                'Internal audit only.',
            ],
            'safety_note' => 'Audit only.',
        ]);

        ClinicReportSection::create([
            'clinic_report_run_id' => $report->id,
            'section_key' => 'overview',
            'category' => 'summary',
            'sort_order' => 1,
            'title' => 'Executive Summary',
            'content' => 'Summary.',
            'metrics' => [
                'visits' => 3,
            ],
        ]);

        ClinicReportRun::create([
            'created_by_id' => $otherDoctor->id,
            'scope_doctor_id' => $otherDoctor->id,
            'report_type' => 'monthly',
            'status' => 'completed',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'title' => 'Other Report',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/clinic-reports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Monthly Clinic Report');

        $this->getJson("/api/clinic-reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.sections.0.section_key', 'overview');

        $this->postJson("/api/clinic-reports/{$report->id}/printed")
            ->assertOk()
            ->assertJsonPath('data.id', $report->id);

        $this->assertDatabaseHas('clinic_report_runs', [
            'id' => $report->id,
        ]);

        $this->assertNotNull($report->fresh()->printed_at);

        $csvResponse = $this->get("/api/clinic-reports/{$report->id}/export.csv");

        $csvResponse->assertOk();
        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString('Metric,Value', $csv);
        $this->assertStringContainsString('visits,3', $csv);
        $this->assertNotNull($report->fresh()->exported_at);

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'clinic_report',
            'action' => 'printed_clinic_report',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'clinic_report',
            'action' => 'exported_clinic_report_csv',
        ]);
    }
}
