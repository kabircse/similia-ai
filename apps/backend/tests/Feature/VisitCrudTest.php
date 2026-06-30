<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_create_visit_for_own_patient(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($doctor);

        $response = $this->postJson("/api/patients/{$patient->id}/visits", [
            'visit_date' => now()->toDateString(),
            'visit_type' => 'initial',
            'status' => 'draft',
            'case_source' => 'manual',
            'chief_complaint' => 'Chilly, low thirst, weight gain.',
            'case_sections' => [
                'generals' => 'Chilly, weight gain',
                'thirst' => 'Low thirst',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.chief_complaint', 'Chilly, low thirst, weight gain.');
    }
}
