<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Str;

class BookSectionChunker
{
    public function chunks(
        string $body,
        ?string $title = null,
        ?string $summary = null,
        int $targetWords = 500,
        int $maxWords = 800
    ): array {
        $body = $this->clean($body);
        $summary = $this->clean($summary);

        if ($body === '' && $summary === '') {
            return [];
        }

        $content = trim(implode("\n\n", array_filter([
            $summary ? "Summary:\n{$summary}" : null,
            $body,
        ])));

        $chunks = [];

        foreach ($this->splitByHeadings($content, $title ?: 'General') as $section) {
            if ($this->wordCount($section['content']) <= $maxWords) {
                $chunks[] = [
                    'title' => $section['title'],
                    'content' => $section['content'],
                ];

                continue;
            }

            foreach ($this->splitLongText($section['content'], $targetWords) as $index => $part) {
                $chunks[] = [
                    'title' => $section['title'].' Part '.($index + 1),
                    'content' => $part,
                ];
            }
        }

        return collect($chunks)
            ->filter(fn ($chunk) => trim($chunk['content']) !== '')
            ->values()
            ->all();
    }

    public function clean(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[ \t]+/', ' ', $value);
        $value = preg_replace("/\n{3,}/", "\n\n", $value);

        return trim($value ?? '');
    }

    private function splitByHeadings(string $content, string $fallbackTitle): array
    {
        $lines = explode("\n", $content);
        $sections = [];
        $currentTitle = $fallbackTitle ?: 'General';
        $currentBody = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^#{1,6}\s+(.+)$/', $trimmed, $matches)) {
                if ($currentBody !== []) {
                    $sections[] = [
                        'title' => $this->normalizeTitle($currentTitle),
                        'content' => trim(implode("\n", $currentBody)),
                    ];
                }

                $currentTitle = trim($matches[1]);
                $currentBody = [];

                continue;
            }

            $currentBody[] = $line;
        }

        if ($currentBody !== []) {
            $sections[] = [
                'title' => $this->normalizeTitle($currentTitle),
                'content' => trim(implode("\n", $currentBody)),
            ];
        }

        if ($sections === []) {
            return [
                [
                    'title' => $this->normalizeTitle($fallbackTitle),
                    'content' => $content,
                ],
            ];
        }

        return $sections;
    }

    private function splitLongText(string $text, int $targetWords): array
    {
        $paragraphs = preg_split("/\n\s*\n/", $text) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = trim($current."\n\n".$paragraph);

            if ($this->wordCount($candidate) > $targetWords && trim($current) !== '') {
                $chunks[] = trim($current);
                $current = trim($paragraph);
            } else {
                $current = $candidate;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function normalizeTitle(string $title): string
    {
        $title = strip_tags($title);
        $title = preg_replace('/[*_`]+/', '', $title) ?? '';
        $title = trim($title);

        return $title === '' ? 'General' : Str::limit($title, 160, '');
    }

    private function wordCount(string $text): int
    {
        return str_word_count(strip_tags($text));
    }
}
