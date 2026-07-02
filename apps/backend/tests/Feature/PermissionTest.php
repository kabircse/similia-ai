<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_has_clinic_settings_permission(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $service = app(PermissionService::class);

        $this->assertTrue($service->has($doctor, 'manage_clinic_settings'));
        $this->assertFalse($service->has($doctor, 'manage_users'));
    }

    public function test_assistant_cannot_access_clinic_settings(): void
    {
        $assistant = User::factory()->create([
            'role' => 'assistant',
        ]);

        $this->actingAs($assistant);

        $this->getJson('/api/clinic-settings')
            ->assertForbidden();
    }

    public function test_user_without_print_permission_cannot_access_print_documents(): void
    {
        $user = User::factory()->create([
            'role' => 'front_desk',
        ]);
        $patient = Patient::factory()->create([
            'doctor_id' => $user->id,
        ]);
        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
        ]);

        $this->actingAs($user);

        $this->getJson("/api/patients/{$patient->id}/visits/{$visit->id}/print/case-sheet")
            ->assertForbidden();
    }
}
