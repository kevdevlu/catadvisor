<?php

namespace App\Support;

use App\Models\ContactNote;
use App\Models\Student;
use Carbon\CarbonImmutable;

/**
 * Where one student stands on outreach, as of the dataset's "today"
 * (Clock::today()): when they were last contacted, whether they are due, and
 * why. The first screen's cards, Status column, and badges all read from
 * this.
 */
final class ContactStatus
{
    // A follow-up date this many days away or less makes a student due.
    public const FOLLOW_UP_WINDOW_DAYS = 14;

    // The "Contacted, last 30 days" card counts contacts this recent.
    public const RECENT_DAYS = 30;

    private function __construct(
        public readonly ?CarbonImmutable $lastContact,
        public readonly ?int $daysSinceContact,
        // Days left before the risk level's target runs out. Negative means
        // overdue; null means the student was never contacted.
        public readonly ?int $daysUntilDue,
        public readonly bool $waiting,
        public readonly bool $followUpDue,
        public readonly ?ContactNote $lastNote,
    ) {}

    /**
     * Expects the student's notes to be loaded. Deleted notes are already
     * left out by the relation.
     */
    public static function for(Student $student): self
    {
        $today = Clock::today();

        // "Note only" entries are not contacts, so they never
        // change the last contact date.
        $lastContactDate = $student->notes
            ->filter(fn (ContactNote $note) => $note->contact_type->isContact())
            ->max(fn (ContactNote $note) => $note->contact_date->toDateString());

        $lastContact = $lastContactDate ? CarbonImmutable::parse($lastContactDate) : null;
        $daysSinceContact = $lastContact ? (int) $lastContact->diffInDays($today) : null;

        return new self(
            lastContact: $lastContact,
            daysSinceContact: $daysSinceContact,
            daysUntilDue: $daysSinceContact === null ? null : $student->risk_flag->targetDays() - $daysSinceContact,
            waiting: $student->awaiting_reply_since !== null,
            followUpDue: self::followUpIsDue($student, $today),
            lastNote: $student->notes
                ->sortByDesc(fn (ContactNote $note) => $note->sortKey())
                ->first(),
        );
    }

    public function neverContacted(): bool
    {
        return $this->lastContact === null;
    }

    public function overdue(): bool
    {
        return $this->daysUntilDue !== null && $this->daysUntilDue < 0;
    }

    /**
     * Due for any reason: never contacted, past the target, waiting on a
     * reply, or a follow-up coming up (the Status help text).
     */
    public function isDue(): bool
    {
        return $this->neverContacted() || $this->overdue() || $this->waiting || $this->followUpDue;
    }

    public function contactedRecently(): bool
    {
        return $this->daysSinceContact !== null && $this->daysSinceContact <= self::RECENT_DAYS;
    }

    /**
     * Due when the follow-up date is 14 days away or less (or already past),
     * or when a follow-up was recorded without a date, for example
     * "withdrawal deadline approaching".
     */
    private static function followUpIsDue(Student $student, CarbonImmutable $today): bool
    {
        if ($student->follow_up_on === null) {
            return $student->follow_up_reason !== null;
        }

        $daysAway = (int) $today->diffInDays(CarbonImmutable::parse($student->follow_up_on->toDateString()), false);

        return $daysAway <= self::FOLLOW_UP_WINDOW_DAYS;
    }
}
