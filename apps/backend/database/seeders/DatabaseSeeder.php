<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoDoctorSeeder::class,
            SampleRepertorySeeder::class,
            SampleMateriaMedicaSeeder::class,
            DemoClinicalCaseSeeder::class,
            WhatsAppMessageTemplateSeeder::class,
        ]);
    }
}
