<?php

namespace App\Http\Resources;

use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * The contact preference, next follow-up, and waiting-on-a-reply details,
 * each with where it came from. The student page shows
 * them as lines, and the first screen shows them in the badge popovers, so
 * both API resources build them here.
 *
 * Expects the source relations to be loaded: see StudentLines::RELATIONS.
 */
final class StudentLines
{
    public const RELATIONS = [
        'contactPreferenceNote',
        'contactPreferenceSetBy',
        // The source notes' students are needed for the readText check.
        'followUpNote.student',
        'followUpSetBy',
        'awaitingReplyNote.student',
    ];

    public static function preference(Student $student): ?array
    {
        if ($student->contact_preference === null) {
            return null;
        }

        return [
            'text' => $student->contact_preference,
            'source' => self::source(
                $student->contactPreferenceNote,
                $student->contactPreferenceSetBy,
                $student->contact_preference_set_at,
                withYear: true,
            ),
        ];
    }

    public static function followUp(Student $student, Advisor $viewer): ?array
    {
        if ($student->follow_up_on === null && $student->follow_up_reason === null) {
            return null;
        }

        // A reason taken from a private note stays with that note's author.
        // Everyone else sees the date only.
        $note = $student->followUpNote;
        $reasonPrivate = $note !== null && $viewer->cannot('readText', $note);

        return [
            'date' => $student->follow_up_on?->toDateString(),
            'date_label' => $student->follow_up_on?->format('M j, Y'),
            'reason' => $reasonPrivate ? null : $student->follow_up_reason,
            'reason_private' => $reasonPrivate,
            // set_by is null when the AI set it.
            'source' => self::source($note, $student->followUpSetBy, $student->follow_up_set_at),
        ];
    }

    public static function waiting(Student $student, Advisor $viewer): ?array
    {
        if ($student->awaiting_reply_since === null) {
            return null;
        }

        $note = $student->awaitingReplyNote;

        return [
            'since' => $student->awaiting_reply_since->toDateString(),
            'since_label' => $student->awaiting_reply_since->format('M j, Y'),
            // The note's words, so the list's "No reply" popover can show why
            // the student owes a reply. Only the AI sets "waiting", from notes
            // it may read, so these are never private words; the readText
            // check still runs, and a deleted note's words are left out.
            'note_text' => $note !== null && ! $note->trashed() && $viewer->can('readText', $note) ? $note->note_text : null,
            // Always set by the AI (or the seeded fixtures standing in for it).
            'source' => self::source($note, null, null),
        ];
    }

    /**
     * Where a line came from. note_id is sent only while the note still
     * exists, so the view links to it; a deleted note keeps its name as
     * plain text.
     */
    private static function source(?ContactNote $note, ?Advisor $setBy, ?CarbonInterface $setAt, bool $withYear = false): array
    {
        return [
            'note_id' => $note !== null && ! $note->trashed() ? $note->id : null,
            'note_name' => $note?->sourceName($withYear),
            'set_by' => $setBy?->display_name,
            'set_on' => $setAt?->format('M j, Y'),
        ];
    }
}
