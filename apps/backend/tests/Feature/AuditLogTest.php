<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_own_activity_logs(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        AuditLog::create([
            'user_id' => $doctor->id,
            'patient_id' => $patient->id,
            'category' => 'patient',
            'action' => 'created',
            'title' => 'Patient created',
            'description' => $patient->name,
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/activity-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Patient created');
    }

    public function test_doctor_cannot_view_another_doctors_activity_logs(): void
    {
        $doctorA = User::factory()->create(['role' => 'doctor']);
        $doctorB = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctorA->id,
        ]);

        AuditLog::create([
            'user_id' => $doctorA->id,
            'patient_id' => $patient->id,
            'category' => 'patient',
            'action' => 'created',
            'title' => 'Patient created',
            'description' => $patient->name,
        ]);

        $this->actingAs($doctorB);

        $this->getJson('/api/activity-logs')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
