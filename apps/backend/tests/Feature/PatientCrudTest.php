<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_create_update_and_view_own_patient(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $this->actingAs($doctor);

        $createResponse = $this->postJson('/api/patients', [
            'name' => 'Test Patient',
            'age_years' => 30,
            'gender' => 'female',
            'phone' => '01700000000',
            'address' => 'Dhaka',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Test Patient');

        $patientId = $createResponse->json('data.id');

        $this->getJson("/api/patients/{$patientId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Test Patient');

        $this->putJson("/api/patients/{$patientId}", [
            'name' => 'Updated Patient',
            'age_years' => 31,
            'gender' => 'female',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Patient');
    }

    public function test_doctor_cannot_view_another_doctors_patient(): void
    {
        $doctorA = User::factory()->create(['role' => 'doctor']);
        $doctorB = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctorA->id,
        ]);

        $this->actingAs($doctorB);

        $this->getJson("/api/patients/{$patient->id}")
            ->assertForbidden();
    }
}
