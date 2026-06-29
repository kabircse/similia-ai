<?php

namespace App\Services\Knowledge;

class SimpleTextEmbedding
{
    public const DIMENSIONS = 384;

    public function embed(string $text): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);

        $text = mb_strtolower($text);
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        $tokens = $matches[0] ?? [];

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }

            $hash = abs(crc32($token));
            $index = $hash % self::DIMENSIONS;
            $sign = ($hash % 2 === 0) ? 1.0 : -1.0;

            $vector[$index] += $sign;
        }

        return $this->normalize($vector);
    }

    public function toPgVector(array $vector): string
    {
        $values = array_map(
            fn ($value) => number_format((float) $value, 6, '.', ''),
            $vector
        );

        return '['.implode(',', $values).']';
    }

    private function normalize(array $vector): array
    {
        $sum = 0.0;

        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        $norm = sqrt($sum);

        if ($norm == 0.0) {
            return $vector;
        }

        return array_map(fn ($value) => $value / $norm, $vector);
    }
}
