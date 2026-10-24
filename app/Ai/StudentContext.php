<?php

namespace App\Ai;

use App\Models\ContactNote;
use App\Models\Student;
use App\Support\Clock;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What the AI may read about one student: the profile without
 * name, email, or ID; the contact preference, next follow-up, and waiting
 * status; and the most recent notes that aren't private. The student's
 * names, email, and student ID are replaced with "the student" wherever they
 * appear, because advisors often write them into notes.
 *
 * Private notes are never included, only counted, so the AI can be told
 * that some were left out.
 *
 * Expects the student's notes and followUpNote to be loaded.
 */
final class StudentContext
{
    // How many recent notes the AI reads. A whole caseload's notes are a
    // few thousand tokens, so this is about focus, not cost.
    public const MAX_NOTES = 10;

    private function __construct(
        public readonly string $input,
        public readonly int $notesUsed,
        public readonly int $privateLeftOut,
        public readonly string $version,
    ) {}

    /**
     * $newNote is the note being checked, marked "(NEW)" in the input.
     */
    public static function for(Student $student, ?ContactNote $newNote = null): self
    {
        $notes = self::readableNotes($student)->take(-self::MAX_NOTES);
        $privateLeftOut = $student->notes->where('is_private', true)->count();
        $anonymize = fn (?string $text) => self::anonymize($student, $text);

        $lines = [
            'Today: '.Clock::today()->toDateString(),
            "Student: {$student->class_year}, {$student->major}, GPA {$student->gpa}, {$student->risk_flag->label()} risk",
            'Contact preference: '.($anonymize($student->contact_preference) ?? 'none'),
            'Next follow-up: '.self::followUpLine($student, $anonymize),
            'Waiting on a reply: '.($student->awaiting_reply_since ? 'since '.$student->awaiting_reply_since->toDateString() : 'no'),
            "Private notes left out: {$privateLeftOut}",
            '',
            'Notes, oldest first:',
            ...$notes->map(fn (ContactNote $note) => sprintf(
                '- %s · %s%s: %s',
                $note->contact_date->toDateString(),
                $note->contact_type->label(),
                $newNote?->is($note) ? ' (NEW)' : '',
                $anonymize($note->note_text),
            )),
        ];

        return new self(
            input: implode("\n", $lines),
            notesUsed: $notes->count(),
            privateLeftOut: $privateLeftOut,
            version: self::versionFor($student),
        );
    }

    /**
     * Which notes and preference a brief was built from. Any change to the
     * readable notes, the preference, or the number of private notes gives
     * a new version, so a cached brief is never stale.
     */
    public static function versionFor(Student $student): string
    {
        return sha1(json_encode([
            self::readableNotes($student)->map(fn (ContactNote $note) => [$note->id, (string) $note->updated_at])->values(),
            $student->contact_preference,
            $student->notes->where('is_private', true)->count(),
        ]));
    }

    /**
     * @return Collection<int, ContactNote>
     */
    private static function readableNotes(Student $student): Collection
    {
        return $student->notes
            ->where('is_private', false)
            ->sortBy(fn (ContactNote $note) => $note->sortKey())
            ->values();
    }

    /**
     * A follow-up taken from a private note is shown only as existing. Its
     * reason came from words the AI may not read.
     */
    private static function followUpLine(Student $student, \Closure $anonymize): string
    {
        if ($student->follow_up_on === null && $student->follow_up_reason === null) {
            return 'none';
        }

        $date = $student->follow_up_on?->toDateString() ?? 'no date';

        if ($student->followUpNote?->is_private) {
            return "{$date} (set from a private note; leave it as it is)";
        }

        return "{$date} · ".$anonymize($student->follow_up_reason);
    }

    /**
     * Replace the student's full name, email, student ID, last name, and
     * first name with "the student", ignoring case. Longest first, so
     * "Jordan Whitfield" becomes one "the student", not two.
     *
     * Known limit: a nickname that isn't in the record ("Gracie" for Grace)
     * slips through.
     */
    private static function anonymize(Student $student, ?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $identifiers = [$student->fullName(), $student->email, $student->sis_id, $student->last_name, $student->first_name];

        foreach ($identifiers as $identifier) {
            // preg_quote: Laravel has no helper to escape a regex.
            $text = Str::replaceMatches('/\b'.preg_quote($identifier, '/').'\b/i', 'the student', $text);
        }

        return $text;
    }
}
