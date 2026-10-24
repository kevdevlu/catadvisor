<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum Risk: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * How often to contact a student at this risk level, in days.
     *
     * These are assumptions, not office policy. The screens show them
     * as plain targets.
     */
    public function targetDays(): int
    {
        return match ($this) {
            self::High => 30,
            self::Medium => 60,
            self::Low => 90,
        };
    }

    /**
     * Sort position, High first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::High => 0,
            self::Medium => 1,
            self::Low => 2,
        };
    }

    public function label(): string
    {
        return Str::ucfirst($this->value);
    }

    /**
     * Read a risk flag as the CSV writes it: High, Medium, or Low.
     */
    public static function fromDataset(string $value): self
    {
        return self::from(Str::lower(Str::trim($value)));
    }
}
