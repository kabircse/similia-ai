<?php

namespace App\Services\Remedies;

class RemedyNormalizer
{
    public function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value);
        $value = str_replace(['.', ',', ';', ':', "'", '"', '’', '`'], '', $value);
        $value = str_replace(['-', '_', '/', '\\'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value ?? '');
    }

    public function codeFromAbbreviationOrName(?string $abbreviation, ?string $name): string
    {
        $base = trim((string) ($abbreviation ?: $name ?: ''));
        $base = str_replace('.', '', $base);
        $base = str_replace([' ', '_'], '-', $base);
        $base = mb_strtolower($base);
        $base = preg_replace('/[^a-z0-9\-]+/', '', $base);
        $base = preg_replace('/\-+/', '-', $base);

        return trim($base ?? '', '-');
    }
}
