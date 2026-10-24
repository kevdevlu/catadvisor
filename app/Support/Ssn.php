<?php

namespace App\Support;

/**
 * Removes US Social Security numbers from free text before it is stored.
 * No one needs an SSN in an advising note. Dataset note N041 has
 * one, written as 000-12-3456.
 */
final class Ssn
{
    // Three, two, and four digits, separated by dashes or spaces.
    private const PATTERN = '/\b\d{3}[- ]\d{2}[- ]\d{4}\b/';

    public const REPLACEMENT = '[SSN removed]';

    public static function redact(string $text): string
    {
        return preg_replace(self::PATTERN, self::REPLACEMENT, $text);
    }
}
