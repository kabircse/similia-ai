<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDoctorSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'doctor@similia.test'],
            [
                'name' => 'Demo Doctor',
                'password' => Hash::make('password'),
                'role' => 'doctor',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@similia.test'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
    }
}