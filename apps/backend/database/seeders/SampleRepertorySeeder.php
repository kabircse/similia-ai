<?php

namespace Database\Seeders;

use App\Models\RepertoryRubric;
use Illuminate\Database\Seeder;

class SampleRepertorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'chapter' => 'Mind',
                'path' => 'Mind > Fear > Cancer',
                'text' => 'Fear of cancer',
                'remedies' => [
                    ['calc', 'Calcarea carbonica', 3],
                    ['ars', 'Arsenicum album', 3],
                    ['carc', 'Carcinosinum', 2],
                    ['nit-ac', 'Nitric acid', 1],
                ],
            ],
            [
                'chapter' => 'Generalities',
                'path' => 'Generalities > Cold > Aggravates',
                'text' => 'Cold aggravates',
                'remedies' => [
                    ['calc', 'Calcarea carbonica', 3],
                    ['sil', 'Silicea', 3],
                    ['nux-v', 'Nux vomica', 2],
                    ['ars', 'Arsenicum album', 2],
                ],
            ],
            [
                'chapter' => 'Stomach',
                'path' => 'Stomach > Desires > Sweets',
                'text' => 'Desire for sweets',
                'remedies' => [
                    ['calc', 'Calcarea carbonica', 3],
                    ['sulph', 'Sulphur', 2],
                    ['lyc', 'Lycopodium', 2],
                    ['arg-n', 'Argentum nitricum', 2],
                ],
            ],
            [
                'chapter' => 'Skin',
                'path' => 'Skin > Cracks > Fingers > Winter',
                'text' => 'Cracks in fingers in winter',
                'remedies' => [
                    ['graph', 'Graphites', 3],
                    ['calc', 'Calcarea carbonica', 2],
                    ['petrol', 'Petroleum', 3],
                    ['sil', 'Silicea', 2],
                ],
            ],
            [
                'chapter' => 'Female',
                'path' => 'Female > Breast > Discharge',
                'text' => 'Breast discharge',
                'remedies' => [
                    ['con', 'Conium maculatum', 3],
                    ['calc', 'Calcarea carbonica', 2],
                    ['phyt', 'Phytolacca', 2],
                    ['graph', 'Graphites', 1],
                ],
            ],
            [
                'chapter' => 'Sleep',
                'path' => 'Sleep > Sleepiness',
                'text' => 'Sleepiness',
                'remedies' => [
                    ['calc', 'Calcarea carbonica', 2],
                    ['nux-m', 'Nux moschata', 3],
                    ['op', 'Opium', 2],
                    ['gels', 'Gelsemium', 2],
                ],
            ],
            [
                'chapter' => 'Dreams',
                'path' => 'Dreams > Work',
                'text' => 'Dreams of work',
                'remedies' => [
                    ['nux-v', 'Nux vomica', 2],
                    ['calc', 'Calcarea carbonica', 1],
                    ['lyc', 'Lycopodium', 1],
                ],
            ],
            [
                'chapter' => 'Generalities',
                'path' => 'Generalities > Obesity',
                'text' => 'Obesity / weight gain tendency',
                'remedies' => [
                    ['calc', 'Calcarea carbonica', 3],
                    ['graph', 'Graphites', 2],
                    ['ant-c', 'Antimonium crudum', 2],
                ],
            ],
        ];

        foreach ($items as $item) {
            $rubric = RepertoryRubric::updateOrCreate(
                ['rubric_path' => $item['path']],
                [
                    'source' => 'sample',
                    'chapter' => $item['chapter'],
                    'rubric_text' => $item['text'],
                ]
            );

            foreach ($item['remedies'] as [$code, $name, $grade]) {
                $rubric->remedies()->updateOrCreate(
                    ['remedy_code' => $code],
                    [
                        'remedy_name' => $name,
                        'grade' => $grade,
                        'source' => 'sample',
                    ]
                );
            }
        }
    }
}