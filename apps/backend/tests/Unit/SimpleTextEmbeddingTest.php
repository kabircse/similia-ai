<?php

namespace Tests\Unit;

use App\Services\Knowledge\SimpleTextEmbedding;
use PHPUnit\Framework\TestCase;

class SimpleTextEmbeddingTest extends TestCase
{
    public function test_it_returns_a_normalized_vector_with_expected_dimensions(): void
    {
        $embedder = new SimpleTextEmbedding;

        $vector = $embedder->embed('Calcarea chilly patient with desire for sweets');
        $norm = sqrt(array_sum(array_map(fn ($value) => $value * $value, $vector)));

        $this->assertCount(SimpleTextEmbedding::DIMENSIONS, $vector);
        $this->assertEqualsWithDelta(1.0, $norm, 0.000001);
    }

    public function test_it_formats_vectors_for_pgvector(): void
    {
        $embedder = new SimpleTextEmbedding;

        $this->assertSame('[0.123457,-1.000000]', $embedder->toPgVector([0.1234567, -1]));
    }
}
