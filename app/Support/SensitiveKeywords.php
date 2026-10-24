<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Finds words in a note that hint at a sensitive topic, for example
 * "counselor" or "stay private". The note form runs the same list
 * in the browser while the advisor types; the server runs it again so the
 * AI's second check knows whether the hint already showed.
 *
 * The list lives in resources/data/sensitive-keywords.json, shared with the
 * browser, so the two can't drift apart.
 */
final class SensitiveKeywords
{
    /**
     * The matched words, lowercased, each once. For example
     * ["counselor", "anxiety", "stay private"].
     *
     * @return list<string>
     */
    public static function find(string $text): array
    {
        return Str::matchAll(self::pattern(), $text)
            ->map(fn (string $word) => Str::lower($word))
            ->unique()
            ->values()
            ->all();
    }

    private static function pattern(): string
    {
        static $pattern;

        // \b and the trailing \w* make each entry match from the start of a
        // word to its end, so "anxi" returns "anxiety", not "anxi".
        return $pattern ??= '/\b(?:'.implode('|', File::json(resource_path('data/sensitive-keywords.json'))['patterns']).')\w*/i';
    }
}
