<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'doctor_id' => User::factory(),
            'name' => fake()->name(),
            'age_years' => fake()->numberBetween(1, 90),
            'gender' => fake()->randomElement(['male', 'female', 'unknown']),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'occupation' => fake()->jobTitle(),
            'marital_status' => 'unknown',
            'notes' => null,
        ];
    }
}
