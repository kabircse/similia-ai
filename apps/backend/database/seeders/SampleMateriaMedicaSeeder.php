<?php

namespace Database\Seeders;

use App\Models\MateriaMedicaChunk;
use App\Services\Knowledge\SimpleTextEmbedding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleMateriaMedicaSeeder extends Seeder
{
    public function run(): void
    {
        $chunks = [
            [
                'code' => 'calc',
                'name' => 'Calcarea carbonica',
                'section' => 'generals',
                'content' => 'Calcarea carbonica is often considered in chilly, easily tired patients with weight gain tendency, low stamina, and sensitivity to cold weather.',
            ],
            [
                'code' => 'calc',
                'name' => 'Calcarea carbonica',
                'section' => 'food',
                'content' => 'Calcarea carbonica may show desire for sweets and eggs, low thirst in some cases, and slow constitutional metabolism.',
            ],
            [
                'code' => 'calc',
                'name' => 'Calcarea carbonica',
                'section' => 'mind',
                'content' => 'Calcarea carbonica may have anxiety about health, fear of disease, fear of cancer, insecurity, and a need for protection.',
            ],
            [
                'code' => 'graph',
                'name' => 'Graphites',
                'section' => 'skin',
                'content' => 'Graphites is often considered where skin is dry, cracked, unhealthy, with fissures especially in folds, fingers, nipples, or behind ears.',
            ],
            [
                'code' => 'graph',
                'name' => 'Graphites',
                'section' => 'generals',
                'content' => 'Graphites patients may be chilly, sluggish, overweight, constipated, and prone to chronic skin eruptions and discharges.',
            ],
            [
                'code' => 'con',
                'name' => 'Conium maculatum',
                'section' => 'glands',
                'content' => 'Conium maculatum is often associated with glandular induration, breast complaints, nodular changes, and hard swelling.',
            ],
            [
                'code' => 'con',
                'name' => 'Conium maculatum',
                'section' => 'female',
                'content' => 'Conium may be considered in breast pain, breast hardness, nipple symptoms, and complaints related to glands.',
            ],
            [
                'code' => 'ars',
                'name' => 'Arsenicum album',
                'section' => 'mind',
                'content' => 'Arsenicum album may show marked anxiety, restlessness, fear of death, fear about health, and need for reassurance.',
            ],
            [
                'code' => 'ars',
                'name' => 'Arsenicum album',
                'section' => 'generals',
                'content' => 'Arsenicum album is commonly chilly, worse after midnight, restless, weak, and may desire small frequent sips of water.',
            ],
            [
                'code' => 'sil',
                'name' => 'Silicea',
                'section' => 'generals',
                'content' => 'Silicea is often chilly, delicate, slow to recover, with weakness, lack of stamina, and tendency to suppuration or chronic complaints.',
            ],
        ];

        $embedder = app(SimpleTextEmbedding::class);

        foreach ($chunks as $chunk) {
            $record = MateriaMedicaChunk::updateOrCreate(
                [
                    'remedy_code' => $chunk['code'],
                    'section' => $chunk['section'],
                    'content' => $chunk['content'],
                ],
                [
                    'source' => 'sample',
                    'source_title' => 'Similia AI Sample Materia Medica',
                    'remedy_name' => $chunk['name'],
                    'metadata' => [
                        'note' => 'Demo educational sample. Replace with licensed/public-domain source later.',
                    ],
                ]
            );

            $vector = $embedder->toPgVector(
                $embedder->embed($chunk['name'].' '.$chunk['section'].' '.$chunk['content'])
            );

            DB::statement(
                'UPDATE materia_medica_chunks SET embedding = ?::vector WHERE id = ?',
                [$vector, $record->id]
            );
        }
    }
}
