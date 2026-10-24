<?php

namespace App\Http\Resources;

use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use App\Support\Clock;
use App\Support\ContactStatus;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One student's page: the profile; the contact preference,
 * follow-up, and waiting-on-a-reply lines, each with where it came from;
 * why the student is due; and the notes, newest first.
 *
 * What the viewer may not see is left out here, before it leaves the server.
 * The words are worked out here too, so the React app does
 * no date math.
 *
 * @mixin Student
 */
class StudentDetailResource extends JsonResource
{
    /**
     * Load everything the page shows, and send "today" for the note form's
     * date field.
     */
    public static function for(Student $student): self
    {
        $student->load([
            'notes.author',
            'contactPreferenceNote',
            'contactPreferenceSetBy',
            'followUpNote',
            'followUpSetBy',
            'awaitingReplyNote',
        ]);

        return (new self($student))->additional(['meta' => [
            'today' => Clock::today()->toDateString(),
        ]]);
    }

    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $followUp = $this->followUpLine($viewer);

        return [
            'id' => $this->id,
            'sis_id' => $this->sis_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'major' => $this->major,
            'class_year' => $this->class_year,
            'credits_earned' => $this->credits_earned,
            'gpa' => $this->gpa,
            'risk' => [
                'value' => $this->risk_flag->value,
                'label' => $this->risk_flag->label(),
            ],
            // Only a yes or no. The other record belongs to another advisor,
            // so nothing about it is sent.
            'possible_duplicate' => $this->possible_duplicate_of !== null,
            'can_write' => $viewer->can('update', $this->resource),
            'contact_preference' => $this->preferenceLine(),
            'follow_up' => $followUp,
            'waiting' => $this->waitingLine(),
            'due' => $this->whyDue(ContactStatus::for($this->resource), $followUp),
            'notes' => NoteResource::collection(
                $this->notes
                    ->sortByDesc(fn (ContactNote $note) => $note->sortKey())
                    ->values()
            ),
        ];
    }

    private function preferenceLine(): ?array
    {
        if ($this->contact_preference === null) {
            return null;
        }

        return [
            'text' => $this->contact_preference,
            'source' => $this->source(
                $this->contactPreferenceNote,
                $this->contactPreferenceSetBy,
                $this->contact_preference_set_at,
                withYear: true,
            ),
        ];
    }

    private function followUpLine(Advisor $viewer): ?array
    {
        if ($this->follow_up_on === null && $this->follow_up_reason === null) {
            return null;
        }

        // A reason taken from a private note stays with that note's author.
        // Everyone else sees the date only.
        $note = $this->followUpNote;
        $reasonPrivate = $note !== null && $viewer->cannot('readText', $note);

        return [
            'date' => $this->follow_up_on?->toDateString(),
            'date_label' => $this->follow_up_on?->format('M j, Y'),
            'reason' => $reasonPrivate ? null : $this->follow_up_reason,
            'reason_private' => $reasonPrivate,
            // set_by is null when the AI set it.
            'source' => $this->source($note, $this->followUpSetBy, $this->follow_up_set_at),
        ];
    }

    private function waitingLine(): ?array
    {
        if ($this->awaiting_reply_since === null) {
            return null;
        }

        return [
            'since' => $this->awaiting_reply_since->toDateString(),
            'since_label' => $this->awaiting_reply_since->format('M j, Y'),
            // Always set by the AI (or the seeded fixtures standing in for it).
            'source' => $this->source($this->awaitingReplyNote, null, null),
        ];
    }

    /**
     * Where a line came from. note_id is sent only while the note still
     * exists, so the view links to it; a deleted note keeps its name as
     * plain text.
     */
    private function source(?ContactNote $note, ?Advisor $setBy, ?CarbonInterface $setAt, bool $withYear = false): array
    {
        return [
            'note_id' => $note !== null && ! $note->trashed() ? $note->id : null,
            'note_name' => $note?->sourceName($withYear),
            'set_by' => $setBy?->display_name,
            'set_on' => $setAt?->format('M j, Y'),
        ];
    }

    /**
     * The "Why due" list, one reason per line, for example "95 days
     * overdue." + "Target for High risk: every 30 days." The emphasis part
     * shows in bold red. A student who isn't due gets one line saying when
     * they will be, and the view titles it "Contact due" instead.
     */
    private function whyDue(ContactStatus $status, ?array $followUp): array
    {
        $risk = $this->risk_flag;
        $target = "Target for {$risk->label()} risk: every {$risk->targetDays()} days.";
        $reasons = [];

        if ($status->neverContacted()) {
            $reasons[] = ['emphasis' => 'Never contacted.', 'text' => $target];
        } elseif ($status->overdue()) {
            $reasons[] = ['emphasis' => self::days(-$status->daysUntilDue).' overdue.', 'text' => $target];
        }

        if ($status->waiting) {
            $reasons[] = ['emphasis' => null, 'text' => 'No reply since '.$this->awaiting_reply_since->format('M j, Y').'.'];
        }

        if ($status->followUpDue) {
            $reasons[] = ['emphasis' => self::followUpDueText($followUp), 'text' => null];
        }

        if ($reasons === []) {
            $when = $status->daysUntilDue === 0 ? 'Due today.' : 'Due in '.self::days($status->daysUntilDue).'.';
            $reasons[] = ['emphasis' => null, 'text' => "{$when} {$target}"];
        }

        return ['is_due' => $status->isDue(), 'reasons' => $reasons];
    }

    /**
     * For example "Follow up by Oct 15, 2026: FAFSA verification.", or
     * "(reason is private)" in place of the reason.
     */
    private static function followUpDueText(array $followUp): string
    {
        $reason = $followUp['reason_private'] ? null : $followUp['reason'];

        return match (true) {
            $followUp['date'] !== null && $reason !== null => "Follow up by {$followUp['date_label']}: {$reason}.",
            $followUp['date'] !== null => "Follow up by {$followUp['date_label']} (reason is private).",
            $reason !== null => "Follow-up due: {$reason} (no date given).",
            default => 'Follow-up due (no date given; reason is private).',
        };
    }

    private static function days(int $count): string
    {
        return $count === 1 ? '1 day' : "{$count} days";
    }
}
