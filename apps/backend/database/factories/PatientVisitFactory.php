<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientVisitFactory extends Factory
{
    protected $model = PatientVisit::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'visit_date' => now()->toDateString(),
            'visit_type' => 'initial',
            'status' => 'draft',
            'case_source' => 'manual',
            'chief_complaint' => 'Demo complaint',
            'raw_case_text' => null,
            'case_sections' => [],
            'missing_questions' => [],
            'red_flags' => [],
            'doctor_notes' => null,
            'next_follow_up_date' => null,
        ];
    }
}
