<?php

namespace App\Services\MateriaMedica;

use Illuminate\Support\Str;

class MateriaMedicaChunker
{
    public function chunks(string $content, int $targetWords = 450, int $maxWords = 750): array
    {
        $content = $this->clean($content);

        if ($content === '') {
            return [];
        }

        $chunks = [];

        foreach ($this->splitByMarkdownHeadings($content) as $section) {
            $sectionTitle = $section['title'];
            $body = $section['body'];

            if ($this->wordCount($body) <= $maxWords) {
                $chunks[] = [
                    'section' => $sectionTitle,
                    'content' => trim($body),
                ];

                continue;
            }

            foreach ($this->splitLongText($body, $targetWords) as $partIndex => $part) {
                $chunks[] = [
                    'section' => $sectionTitle.' Part '.($partIndex + 1),
                    'content' => trim($part),
                ];
            }
        }

        return collect($chunks)
            ->filter(fn ($chunk) => trim($chunk['content']) !== '')
            ->values()
            ->all();
    }

    public function clean(?string $content): string
    {
        $content = (string) $content;
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return trim($content ?? '');
    }

    private function splitByMarkdownHeadings(string $content): array
    {
        $lines = explode("\n", $content);
        $sections = [];
        $currentTitle = 'General';
        $currentBody = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^#{1,6}\s+(.+)$/', $trimmed, $matches)) {
                if ($currentBody !== []) {
                    $sections[] = [
                        'title' => $this->normalizeSectionTitle($currentTitle),
                        'body' => trim(implode("\n", $currentBody)),
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
                'title' => $this->normalizeSectionTitle($currentTitle),
                'body' => trim(implode("\n", $currentBody)),
            ];
        }

        if ($sections === []) {
            return [
                [
                    'title' => 'General',
                    'body' => $content,
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
                $current = $paragraph;
            } else {
                $current = $candidate;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function normalizeSectionTitle(string $title): string
    {
        $title = strip_tags($title);
        $title = trim($title);
        $title = preg_replace('/[*_`]+/', '', $title) ?? '';

        return $title === '' ? 'General' : Str::limit($title, 120, '');
    }

    private function wordCount(string $text): int
    {
        return str_word_count(strip_tags($text));
    }
}
