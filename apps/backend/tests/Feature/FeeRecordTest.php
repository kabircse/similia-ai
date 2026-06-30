<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_record_calculates_total_due_and_status(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($doctor);

        $response = $this->putJson("/api/patients/{$patient->id}/visits/{$visit->id}/fee", [
            'currency' => 'BDT',
            'consultation_fee' => 3000,
            'medicine_fee' => 0,
            'discount_amount' => 500,
            'paid_amount' => 1000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_amount', '2500.00')
            ->assertJsonPath('data.paid_amount', '1000.00')
            ->assertJsonPath('data.due_amount', '1500.00')
            ->assertJsonPath('data.payment_status', 'partial');
    }
}
