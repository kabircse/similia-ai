<?php

namespace Tests\Feature;

use App\Jobs\CompareMateriaMedicaJob;
use App\Jobs\StructureCaseJob;
use App\Models\AiTask;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_queue_case_structuring_task(): void
    {
        Queue::fake();

        $doctor = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'raw_case_text' => 'Chilly, low thirst, fear of cancer.',
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/structure-case/async")
            ->assertOk()
            ->assertJsonPath('data.type', 'structure_case')
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('ai_tasks', [
            'patient_visit_id' => $visit->id,
            'type' => 'structure_case',
            'status' => 'queued',
        ]);

        Queue::assertPushed(StructureCaseJob::class);
    }

    public function test_doctor_can_queue_materia_medica_comparison_task(): void
    {
        Queue::fake();

        $doctor = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/materia-medica/compare/async", [
            'method' => 'weighted',
            'limit' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'compare_materia_medica')
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('ai_tasks', [
            'patient_visit_id' => $visit->id,
            'type' => 'compare_materia_medica',
            'status' => 'queued',
        ]);

        Queue::assertPushed(CompareMateriaMedicaJob::class);
    }

    public function test_doctor_can_view_own_ai_task(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $task = AiTask::create([
            'user_id' => $doctor->id,
            'type' => 'structure_case',
            'status' => 'queued',
            'title' => 'AI case structuring',
        ]);

        $this->actingAs($doctor);

        $this->getJson("/api/ai-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);
    }
}
