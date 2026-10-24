<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ContactType: string
{
    case Phone = 'phone';
    case Email = 'email';
    case Zoom = 'zoom';
    case InPerson = 'in_person';
    case NoteOnly = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::Zoom => 'Zoom',
            self::InPerson => 'In person',
            self::NoteOnly => 'Note only',
        };
    }

    /**
     * A "Note only" entry records something without contacting the student,
     * so it never counts as a contact.
     */
    public function isContact(): bool
    {
        return $this !== self::NoteOnly;
    }

    /**
     * Contact where the student could answer on the spot. A newer one of
     * these clears "waiting on a reply". A phone note the AI reads
     * as a missed call ("Left voicemail") is handled by the AI check instead.
     */
    public function isTwoWay(): bool
    {
        return in_array($this, [self::Phone, self::Zoom, self::InPerson], true);
    }

    /**
     * Read a contact type as the CSV and the UI write it, for example
     * "In person".
     */
    public static function fromLabel(string $label): self
    {
        foreach (self::cases() as $case) {
            if (Str::lower($case->label()) === Str::lower(Str::trim($label))) {
                return $case;
            }
        }

        throw new \ValueError("Unknown contact type: {$label}");
    }
}
