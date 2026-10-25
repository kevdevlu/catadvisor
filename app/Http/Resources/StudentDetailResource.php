<?php

namespace App\Http\Resources;

use App\Ai\AdvisingAssistant;
use App\Ai\StudentContext;
use App\Models\ContactNote;
use App\Models\Student;
use App\Support\Clock;
use App\Support\ContactStatus;
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
        $student->load(['notes.author', ...StudentLines::RELATIONS]);

        return (new self($student))->additional(['meta' => [
            'today' => Clock::today()->toDateString(),
        ]]);
    }

    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $followUp = StudentLines::followUp($this->resource, $viewer);

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
            // Changes whenever the AI brief would, and when an answer held
            // for the advisor is let go; the page reloads the
            // brief when it does.
            'brief_version' => StudentContext::versionFor($this->resource).(AdvisingAssistant::holdsAnswer($this->resource) ? ':held' : ''),
            'contact_preference' => StudentLines::preference($this->resource),
            'follow_up' => $followUp,
            'waiting' => StudentLines::waiting($this->resource, $viewer),
            'due' => $this->whyDue(ContactStatus::for($this->resource), $followUp),
            'notes' => NoteResource::collection(
                $this->notes
                    ->sortByDesc(fn (ContactNote $note) => $note->sortKey())
                    ->values()
            ),
        ];
    }

    /**
     * The "Reasons to reach out" list, one reason per line, for example "95 days past
     * target." + "Target for High risk: every 30 days." The emphasis part
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
            $reasons[] = ['emphasis' => self::days(-$status->daysUntilDue).' past target.', 'text' => $target];
        }

        if ($status->waiting) {
            $reasons[] = ['emphasis' => null, 'text' => 'No reply since '.$this->awaiting_reply_since->format('M j, Y').'.'];
        }

        if ($status->followUpDue) {
            $reasons[] = ['emphasis' => self::followUpDueText($followUp), 'text' => null];
        }

        if ($reasons === []) {
            $when = $status->daysUntilDue === 0 ? 'Target is today.' : self::days($status->daysUntilDue).' until target.';
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
            $reason !== null => "Follow-up needed: {$reason} (no date given).",
            default => 'Follow-up needed (no date given; reason is private).',
        };
    }

    private static function days(int $count): string
    {
        return $count === 1 ? '1 day' : "{$count} days";
    }
}
