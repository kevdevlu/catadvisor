<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

/**
 * Reads the dates in the dataset CSVs, which mix five formats:
 * 2026-09-18, Aug 25, 2026, 2026/09/02, 30-Aug-2026, 09/16/2026.
 * The month-name format also appears with a zero-padded day (Mar 05, 2026),
 * so it is listed both ways.
 */
final class DatasetDate
{
    private const FORMATS = ['Y-m-d', 'M j, Y', 'M d, Y', 'Y/m/d', 'd-M-Y', 'm/d/Y'];

    /**
     * Returns null for a blank or "N/A" value, which the dataset uses for a
     * student who was never contacted.
     */
    public static function parse(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        if ($value === '' || strtoupper($value) === 'N/A') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            // The leading "!" sets the time to midnight instead of now. The
            // round trip rejects loose matches, for example 09/16/2026 read
            // with a day-first format.
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($date->format($format) === $value) {
                return $date;
            }
        }

        throw new InvalidArgumentException("Unrecognized date in the dataset: {$value}");
    }
}
