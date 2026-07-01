<?php

namespace App\Support;

class AiResponseLanguages
{
    private const ALLOWED = [
        'auto',
        'en-US',
        'bn-BD',
        'hi-IN',
        'ur-PK',
    ];

    private const ALIASES = [
        'en' => 'en-US',
        'english' => 'en-US',
        'bn' => 'bn-BD',
        'bangla' => 'bn-BD',
        'bengali' => 'bn-BD',
        'hi' => 'hi-IN',
        'hindi' => 'hi-IN',
        'ur' => 'ur-PK',
        'urdu' => 'ur-PK',
    ];

    public static function allowed(): array
    {
        return self::ALLOWED;
    }

    public static function normalize(?string $language): string
    {
        $language = trim((string) $language);

        if ($language === '') {
            return 'auto';
        }

        if (in_array($language, self::ALLOWED, true)) {
            return $language;
        }

        $normalizedKey = strtolower(str_replace('_', '-', $language));

        return self::ALIASES[$normalizedKey] ?? 'auto';
    }

    public static function resolved(?string $language): ?string
    {
        $language = self::normalize($language);

        return $language === 'auto' ? null : $language;
    }
}
