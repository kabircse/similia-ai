<?php

namespace App\Services\Knowledge;

class KnowledgeTypeClassifier
{
    public function classify(?string $code, ?string $title, ?string $author = null): string
    {
        $text = mb_strtolower(trim(($code ?? '').' '.($title ?? '').' '.($author ?? '')));

        if (str_contains($text, 'organon')) {
            return 'organon';
        }

        if (
            str_contains($text, 'potency') ||
            str_contains($text, 'posology') ||
            str_contains($text, 'repetition') ||
            str_contains($text, 'dose')
        ) {
            return 'potency';
        }

        if (
            str_contains($text, 'philosophy') ||
            str_contains($text, 'kent') ||
            str_contains($text, 'aphorism') ||
            str_contains($text, 'chronic disease')
        ) {
            return 'philosophy';
        }

        if (
            str_contains($text, 'relationship') ||
            str_contains($text, 'remedies relationship') ||
            str_contains($text, 'remedy relationship') ||
            str_contains($text, 'miller')
        ) {
            return 'relationship';
        }

        if (
            str_contains($text, 'medicine') ||
            str_contains($text, 'pathology') ||
            str_contains($text, 'diagnosis') ||
            str_contains($text, 'clinical') ||
            str_contains($text, 'red flag')
        ) {
            return 'medical';
        }

        return 'general';
    }
}
