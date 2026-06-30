<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\CaseRubric;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\PatientFee;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\RepertoryRubric;
use App\Models\User;
use App\Services\Repertorization\CrossRepertorizationEngine;
use App\Services\Repertorization\EliminativeRepertorizationEngine;
use App\Services\Repertorization\WeightedRepertorizationEngine;
use Illuminate\Database\Seeder;

class DemoClinicalCaseSeeder extends Seeder
{
    public function run(): void
    {
        $doctor = User::where('email', 'doctor@similia.test')->firstOrFail();

        ClinicSetting::updateOrCreate(
            [
                'doctor_id' => $doctor->id,
            ],
            [
                'clinic_name' => 'Similia AI Demo Clinic',
                'tagline' => 'AI Clinical Workspace for Classical Homeopathy',
                'doctor_display_name' => 'Demo Doctor',
                'doctor_qualification' => 'D.H.M.S',
                'phone' => '+8801700000000',
                'email' => 'doctor@similia.test',
                'website' => 'https://similia-ai.test',
                'address' => 'Dhaka, Bangladesh',
                'default_currency' => 'BDT',
                'default_consultation_fee' => 3000,
                'default_followup_fee' => 2000,
                'medicine_fee_included' => true,
                'prescription_footer' => 'Please follow the doctor-approved instructions. Return for follow-up on the advised date.',
                'case_sheet_footer' => 'Private clinical case sheet for practitioner use only.',
            ]
        );

        $patient = Patient::updateOrCreate(
            [
                'doctor_id' => $doctor->id,
                'phone' => '01700000001',
            ],
            [
                'name' => 'Demo Patient - Constitutional Case',
                'age_years' => 26,
                'gender' => 'female',
                'address' => 'Dhaka, Bangladesh',
                'occupation' => 'Student',
                'marital_status' => 'married',
                'emergency_contact' => '01700000002',
                'notes' => 'Demo patient created for Similia AI product walkthrough.',
            ]
        );

        $visit = PatientVisit::updateOrCreate(
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'visit_date' => now()->toDateString(),
            ],
            [
                'visit_type' => 'initial',
                'status' => 'completed',
                'case_source' => 'mixed',
                'chief_complaint' => 'Weight gain, chilly tendency, cracked fingers in winter, fear of cancer, and left breast discharge.',
                'raw_case_text' => 'Female 26. Chilly. Low thirst. Weight gain. Likes sweets. Fear of cancer. Cracked fingers in winter. Left breast discharge. Sleepy. Dreams of daily work.',
                'case_sections' => [
                    'mentals' => 'Fear of cancer. Anxiety about health.',
                    'generals' => 'Chilly patient. Weight gain. Easily tired. Sleepy.',
                    'thermal_state' => 'Chilly.',
                    'thirst' => 'Low thirst.',
                    'food_desires' => 'Likes sweets.',
                    'sleep' => 'Sleepy, sleeps more.',
                    'dreams' => 'Dreams of daily work.',
                    'location' => 'Fingers, left breast.',
                    'sensation' => 'Cracks in fingers. Breast discharge.',
                    'modalities' => 'Cracks worse in winter.',
                    'concomitants' => 'Breast discharge with constitutional symptoms.',
                    'past_history' => 'Demo case only.',
                    'family_history' => 'Fear of cancer due to family concern.',
                    'reports_note' => 'Clinical red flag: breast discharge should be medically evaluated.',
                ],
                'missing_questions' => [
                    'What makes the breast complaint better or worse?',
                    'Is there any lump, pain, fever, or blood-stained discharge?',
                    'What is the exact menstrual history?',
                    'How is stool, urine, perspiration, and appetite?',
                ],
                'red_flags' => [
                    'Breast discharge should be medically evaluated.',
                ],
                'doctor_notes' => 'Demo case showing full workflow: case-taking, rubrics, repertorization, materia medica, prescription, fee, print, and timeline.',
                'next_follow_up_date' => now()->addWeeks(4)->toDateString(),
            ]
        );

        $visit->caseRubrics()->delete();
        $visit->repertorizationRuns()->delete();

        $rubrics = [
            [
                'path' => 'Mind > Fear > Cancer',
                'symptom_type' => 'mental',
                'importance' => 'essential',
                'weight' => 5,
                'is_essential' => true,
            ],
            [
                'path' => 'Generalities > Cold > Aggravates',
                'symptom_type' => 'general',
                'importance' => 'essential',
                'weight' => 4,
                'is_essential' => true,
            ],
            [
                'path' => 'Stomach > Desires > Sweets',
                'symptom_type' => 'general',
                'importance' => 'important',
                'weight' => 3,
                'is_essential' => false,
            ],
            [
                'path' => 'Skin > Cracks > Fingers > Winter',
                'symptom_type' => 'particular',
                'importance' => 'important',
                'weight' => 3,
                'is_essential' => false,
            ],
            [
                'path' => 'Female > Breast > Discharge',
                'symptom_type' => 'particular',
                'importance' => 'important',
                'weight' => 3,
                'is_essential' => false,
            ],
            [
                'path' => 'Generalities > Obesity',
                'symptom_type' => 'general',
                'importance' => 'supportive',
                'weight' => 2,
                'is_essential' => false,
            ],
            [
                'path' => 'Sleep > Sleepiness',
                'symptom_type' => 'general',
                'importance' => 'supportive',
                'weight' => 2,
                'is_essential' => false,
            ],
            [
                'path' => 'Dreams > Work',
                'symptom_type' => 'mental',
                'importance' => 'supportive',
                'weight' => 1,
                'is_essential' => false,
            ],
        ];

        foreach ($rubrics as $item) {
            $rubric = RepertoryRubric::where('rubric_path', $item['path'])->first();

            if (! $rubric) {
                continue;
            }

            CaseRubric::create([
                'patient_visit_id' => $visit->id,
                'repertory_rubric_id' => $rubric->id,
                'doctor_id' => $doctor->id,
                'symptom_type' => $item['symptom_type'],
                'importance' => $item['importance'],
                'weight' => $item['weight'],
                'is_essential' => $item['is_essential'],
                'note' => 'Demo selected rubric.',
            ]);
        }

        $weightedRun = app(WeightedRepertorizationEngine::class)->run($visit, $doctor, [
            'limit' => 10,
        ]);

        app(CrossRepertorizationEngine::class)->run($visit, $doctor, [
            'limit' => 10,
        ]);

        app(EliminativeRepertorizationEngine::class)->run($visit, $doctor, [
            'limit' => 10,
            'strict_essential' => true,
        ]);

        $topResult = $weightedRun->results()->orderBy('rank')->first();

        PatientPrescription::updateOrCreate(
            [
                'patient_visit_id' => $visit->id,
            ],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'repertorization_run_id' => $weightedRun->id,
                'repertorization_result_id' => $topResult?->id,
                'source_method' => 'weighted',
                'remedy_code' => $topResult?->remedy_code ?? 'calc',
                'remedy_name' => $topResult?->remedy_name ?? 'Calcarea carbonica',
                'potency' => '200C',
                'repetition' => 'Single dose',
                'dose_instruction' => 'Take one dose as instructed by the physician. Do not repeat without follow-up.',
                'reason' => 'Demo prescription selected from repertorization result and constitutional totality.',
                'advice' => 'Observe changes in energy, sleep, skin cracks, anxiety, and breast complaint. Seek medical evaluation for breast discharge.',
                'food_lifestyle_note' => 'Maintain regular sleep, avoid unnecessary self-medication, and report any new symptom.',
                'follow_up_date' => now()->addWeeks(4)->toDateString(),
                'status' => 'final',
                'finalized_at' => now(),
            ]
        );

        PatientFee::updateOrCreate(
            [
                'patient_visit_id' => $visit->id,
            ],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'currency' => 'BDT',
                'consultation_fee' => 3000,
                'medicine_fee' => 0,
                'discount_amount' => 0,
                'total_amount' => 3000,
                'paid_amount' => 3000,
                'due_amount' => 0,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'payment_date' => now()->toDateString(),
                'note' => 'Demo fee record.',
            ]
        );

        AuditLog::updateOrCreate(
            [
                'user_id' => $doctor->id,
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit->id,
                'category' => 'demo',
                'action' => 'created',
                'title' => 'Demo clinical workflow created',
            ],
            [
                'entity_type' => PatientVisit::class,
                'entity_id' => $visit->id,
                'description' => 'Demo patient, visit, rubrics, repertorization, prescription, fee, print, and timeline are ready.',
                'metadata' => [
                    'patient_name' => $patient->name,
                    'visit_date' => $visit->visit_date?->toDateString(),
                ],
            ]
        );
    }
}
