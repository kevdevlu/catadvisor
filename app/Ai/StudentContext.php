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
 * appear, because advisors often write them into notes. Any other email
 * address or phone number in the text is replaced too.
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
        // The notes in the input, which count as read by the AI.
        public readonly array $noteIds,
        public readonly int $privateLeftOut,
        // Which notes the input was built from, to tell later whether a
        // newer note has arrived since (see AdvisingAssistant).
        public readonly string $notesSignature,
        // The new note's words as the AI saw them, to check the quotes it
        // gives as evidence.
        public readonly ?string $newNoteText,
        // Which edit of the new note the AI read (ContactNote::$revision).
        public readonly ?int $newNoteRevision,
    ) {}

    /**
     * $newNote is the note being checked, marked "(NEW)" in the input. It is
     * always included, even when it is older than the most recent notes (an
     * edited or backdated note).
     */
    public static function for(Student $student, ?ContactNote $newNote = null): self
    {
        $readable = self::readableNotes($student);
        $notes = $readable->take(-self::MAX_NOTES);

        if ($newNote && ! $notes->contains(fn (ContactNote $note) => $note->is($newNote))) {
            $notes = $readable->take(-(self::MAX_NOTES - 1))
                ->push($readable->first(fn (ContactNote $note) => $note->is($newNote)) ?? $newNote)
                ->sortBy(fn (ContactNote $note) => $note->sortKey())
                ->values();
        }

        $privateLeftOut = $student->notes->where('is_private', true)->count();
        $anonymize = fn (?string $text) => self::anonymize($student, $text);

        $lines = collect([
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
        ]);

        return new self(
            input: $lines->implode("\n"),
            notesUsed: $notes->count(),
            noteIds: $notes->modelKeys(),
            privateLeftOut: $privateLeftOut,
            notesSignature: self::notesSignature($student),
            newNoteText: $newNote ? $anonymize($newNote->note_text) : null,
            newNoteRevision: $newNote?->revision,
        );
    }

    /**
     * What applying an answer later needs to know about the input it came
     * from (see AdvisingAssistant::keep()).
     *
     * @return array{signature: string, newNoteText: ?string, revision: ?int, notesUsed: int, privateLeftOut: int}
     */
    public function meta(): array
    {
        return [
            'signature' => $this->notesSignature,
            'newNoteText' => $this->newNoteText,
            'revision' => $this->newNoteRevision,
            'notesUsed' => $this->notesUsed,
            'privateLeftOut' => $this->privateLeftOut,
        ];
    }

    /**
     * What a brief was built from: the readable notes, the number of
     * private notes, the contact preference, the next follow-up, and
     * "waiting on a reply". Any change to them gives a new version, so a
     * cached brief is never stale.
     */
    public static function versionFor(Student $student): string
    {
        $followUpReason = $student->followUpNote?->is_private ? '(private)' : $student->follow_up_reason;

        return sha1(json_encode([
            self::notesSignature($student),
            $student->contact_preference,
            $student->follow_up_on?->toDateString(),
            $followUpReason,
            $student->awaiting_reply_since?->toDateString(),
        ]));
    }

    public static function notesSignature(Student $student): string
    {
        return sha1(json_encode([
            self::readableNotes($student)->map(fn (ContactNote $note) => [$note->id, (string) $note->updated_at])->values(),
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
     * first name with "the student", ignoring case, then each part of a
     * name in two or more parts ("Sayed" for Al-Sayed). Longest first, so
     * "Jordan Whitfield" becomes one "the student", not two. Then replace
     * any other email address or phone number.
     *
     * This errs toward removing too much: a last name that is also a word
     * goes too, so "Pell Grant" becomes "Pell the student" for a student
     * named Grant. Parts of 2 letters or fewer ("Al", "O") are left, since
     * they match too many words. Known limit: a nickname that isn't in the
     * record ("Gracie" for Grace) slips through.
     */
    private static function anonymize(Student $student, ?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // "S1011" also as "S-1011" or "S 1011".
        $studentId = Str::replaceMatches('/^(\p{L}+)(\d+)$/u', '$1 $2', $student->sis_id);
        $nameParts = Str::of("{$student->last_name} {$student->first_name}")->split('/[\s-]+/')
            ->filter(fn (string $part) => Str::length(Str::replace(["'", '’'], '', $part)) > 2);

        $identifiers = [$student->fullName(), $student->email, $studentId, $student->last_name, $student->first_name, ...$nameParts];

        foreach ($identifiers as $identifier) {
            $text = Str::replaceMatches(self::identifierPattern($identifier), 'the student', $text);
        }

        $text = Str::replaceMatches('/[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.\p{L}{2,}/iu', '[email]', $text);

        // Ten digits, with or without separators: "520-555-0142",
        // "(520) 555 0142", "5205550142".
        return Str::replaceMatches('/(?<!\d)(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}(?!\d)/u', '[phone]', $text);
    }

    /**
     * A pattern for one name or ID, ignoring case, accented letters
     * included. A space or hyphen inside it also matches the other, or
     * nothing ("Al Sayed", "Al-Sayed", "Alsayed"). An apostrophe matches a
     * straight or curly one (Word and Outlook type the curly one), a space,
     * or nothing ("O'Connor", "O’Connor", "O Connor", "OConnor"). It must
     * stand alone: no letter or digit right before or after it, so
     * possessives ("Jordan's") still match.
     */
    private static function identifierPattern(string $identifier): string
    {
        $pieces = Str::of($identifier)->squish()->split('/[\s-]+/')
            // preg_quote: Laravel has no helper to escape a regex.
            ->map(fn (string $piece) => Str::swap(["'" => "['’\s]?", '’' => "['’\s]?"], preg_quote($piece, '/')));

        return '/(?<![\p{L}\p{N}])'.$pieces->implode('[\s-]?').'(?![\p{L}\p{N}])/iu';
    }
}
