<?php

namespace Tests\Unit;

use App\Models\CaseRubric;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\RepertoryRubric;
use App\Models\RepertoryRubricRemedy;
use App\Models\User;
use App\Services\Repertorization\WeightedRepertorizationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightedRepertorizationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_engine_ranks_remedies_by_weight_times_grade(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $rubric = RepertoryRubric::create([
            'source' => 'test',
            'chapter' => 'Mind',
            'rubric_path' => 'Mind > Fear > Cancer',
            'rubric_text' => 'Fear of cancer',
        ]);

        RepertoryRubricRemedy::create([
            'repertory_rubric_id' => $rubric->id,
            'remedy_code' => 'calc',
            'remedy_name' => 'Calcarea carbonica',
            'grade' => 3,
            'source' => 'test',
        ]);

        RepertoryRubricRemedy::create([
            'repertory_rubric_id' => $rubric->id,
            'remedy_code' => 'ars',
            'remedy_name' => 'Arsenicum album',
            'grade' => 2,
            'source' => 'test',
        ]);

        CaseRubric::create([
            'patient_visit_id' => $visit->id,
            'repertory_rubric_id' => $rubric->id,
            'doctor_id' => $doctor->id,
            'symptom_type' => 'mental',
            'importance' => 'essential',
            'weight' => 5,
            'is_essential' => true,
        ]);

        $run = app(WeightedRepertorizationEngine::class)->run($visit, $doctor, [
            'limit' => 10,
        ]);

        $top = $run->results()->orderBy('rank')->first();

        $this->assertSame('calc', $top->remedy_code);
        $this->assertSame(15, $top->total_score);
    }
}
