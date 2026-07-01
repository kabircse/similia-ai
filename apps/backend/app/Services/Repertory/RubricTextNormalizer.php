<?php

namespace App\Services\Repertory;

use Illuminate\Support\Str;

class RubricTextNormalizer
{
    public function normalizeText(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value ?? '');
    }

    public function pathFromLegacy(?string $chapter, ?string $textEn): string
    {
        $chapter = $this->normalizeText($chapter);
        $textEn = $this->normalizeText($textEn);

        if ($textEn === '') {
            return Str::headline($chapter);
        }

        if ($this->same($chapter, $textEn)) {
            return Str::headline($chapter);
        }

        $parts = collect(explode(',', $textEn))
            ->map(fn ($part) => $this->normalizeText($part))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return Str::headline($chapter);
        }

        if ($this->same($parts->first(), $chapter)) {
            $parts = $parts->slice(1)->values();
        }

        $pathParts = collect([Str::headline($chapter)])
            ->merge($parts->map(fn ($part) => ucfirst($part)))
            ->filter()
            ->values();

        return $pathParts->join(' > ');
    }

    public function isSelectable(?string $chapter, ?string $textEn, int $medicineCount, ?string $rubricType): bool
    {
        $chapter = $this->normalizeText($chapter);
        $textEn = $this->normalizeText($textEn);
        $rubricType = mb_strtolower($this->normalizeText($rubricType));

        if ($medicineCount <= 0) {
            return false;
        }

        if ($this->same($chapter, $textEn)) {
            return false;
        }

        if (in_array($rubricType, ['chapter', 'section', 'root', 'heading'], true)) {
            return false;
        }

        return true;
    }

    public function same(?string $a, ?string $b): bool
    {
        return mb_strtolower($this->normalizeText($a)) === mb_strtolower($this->normalizeText($b));
    }
}
