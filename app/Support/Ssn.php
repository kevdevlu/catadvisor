<?php

namespace App\Support;

/**
 * Removes US Social Security numbers from free text before it is stored.
 * No one needs an SSN in an advising note. Dataset note N041 has
 * one, written as 000-12-3456. The models call this on every save (see
 * ContactNote::noteText() and Student::contactPreference()), so no code path
 * can store one by forgetting.
 */
final class Ssn
{
    // Three, two, and four digits, with dashes, dots, spaces, or nothing
    // between them: 000-12-3456, 000.12.3456, 000 12 3456, 000123456. A phone
    // number (3-3-4, or 10 digits in a row) does not match.
    private const PATTERN = '/\b\d{3}[-. ]?\d{2}[-. ]?\d{4}\b/';

    public const REPLACEMENT = '[SSN removed]';

    public static function redact(string $text): string
    {
        return preg_replace(self::PATTERN, self::REPLACEMENT, $text);
    }
}
