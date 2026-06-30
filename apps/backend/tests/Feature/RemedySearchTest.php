<?php

namespace Tests\Feature;

use App\Models\Remedy;
use App\Models\User;
use App\Services\Remedies\RemedyNormalizer;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemedySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_remedies(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $normalizer = app(RemedyNormalizer::class);

        $remedy = Remedy::create([
            'code' => 'abrot',
            'name' => 'Abrotanum',
            'abbreviation' => 'Abrot.',
            'normalized_name' => $normalizer->normalize('Abrotanum'),
            'normalized_abbreviation' => $normalizer->normalize('Abrot.'),
            'source' => 'test',
        ]);

        app(RemedyResolver::class)->syncDefaultAliases($remedy, 'test');

        $this->actingAs($doctor);

        $this->getJson('/api/remedies?q=Abrot')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Abrotanum');
    }
}
