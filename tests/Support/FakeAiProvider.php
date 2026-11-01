<?php

namespace Tests\Support;

use App\Services\AI\AiProviderInterface;
use RuntimeException;

/**
 * Deterministic fake provider so tests never touch live Gemini.
 * Configure behaviour per-test via the static helpers.
 */
class FakeAiProvider implements AiProviderInterface
{
    /** @var list<string> */
    private static array $textResponses = [];

    /** @var list<array> */
    private static array $structuredResponses = [];

    /** @var list<string> */
    private static array $failures = [];

    public static function reset(): void
    {
        self::$textResponses = [];
        self::$structuredResponses = [];
        self::$failures = [];
    }

    public static function respondText(string $text): void
    {
        self::$textResponses[] = $text;
    }

    public static function respondStructured(mixed $data): void
    {
        self::$structuredResponses[] = $data;
    }

    /** Force the next generate() call to throw (provider timeouts/unavailable). */
    public static function failNext(): void
    {
        self::$failures[] = 'generate';
    }

    /** Force the next generateStructured() call to return malformed (non-array) output. */
    public static function failStructuredNext(): void
    {
        self::$failures[] = 'structured';
    }

    public function generate(string $system, string $prompt): string
    {
        if (in_array('generate', self::$failures, true)) {
            array_shift(self::$failures);
            throw new RuntimeException('The AI provider is temporarily unavailable.');
        }
        foreach (self::$failures as $i => $f) {
            if ($f === 'generate') { unset(self::$failures[$i]); self::$failures = array_values(self::$failures); break; }
        }
        return array_shift(self::$textResponses) ?? 'fallback fake response';
    }

    public function generateStructured(string $system, string $prompt): array
    {
        if (in_array('structured', self::$failures, true)) {
            array_shift(self::$failures);
            throw new RuntimeException('The AI provider returned malformed structured data.');
        }
        foreach (self::$failures as $i => $f) {
            if ($f === 'structured') { unset(self::$failures[$i]); self::$failures = array_values(self::$failures); break; }
        }
        $data = array_shift(self::$structuredResponses) ?? ['struct' => 'fallback'];
        if (!is_array($data)) {
            throw new RuntimeException('The AI provider returned malformed structured data.');
        }
        return $data;
    }
}
