<?php

namespace App\Support;

use Illuminate\Support\Str;

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
    // between them: 000-12-3456, 000.12.3456, 000 12 3456, 000123456. The
    // dashes include en and em dashes (\x{2013}, \x{2014}), which Word and
    // Outlook put in when text is pasted. A phone number (3-3-4, or 10
    // digits in a row) does not match. The digits only need a non-digit on
    // each side, so "SSN123-45-6789" is caught too.
    private const PATTERN = '/(?<!\d)\d{3}[-.\s\x{2013}\x{2014}]?\d{2}[-.\s\x{2013}\x{2014}]?\d{4}(?!\d)/u';

    public const REPLACEMENT = '[SSN removed]';

    public static function redact(string $text): string
    {
        return Str::replaceMatches(self::PATTERN, self::REPLACEMENT, $text);
    }
}
