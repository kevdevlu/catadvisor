<?php

namespace App\Support;

use App\Enums\Risk;
use App\Models\Student;
use Illuminate\Support\Str;

/**
 * One row of the first screen: a student and their contact status, with the
 * card and search rules that decide whether the row is shown.
 */
final class CaseloadEntry
{
    // The four count cards, plus "recent", which replaces "never" as the
    // fourth card when nobody is never-contacted.
    public const CARDS = ['due', 'high_overdue', 'waiting', 'never', 'recent'];

    public function __construct(
        public readonly Student $student,
        public readonly ContactStatus $status,
    ) {}

    public static function for(Student $student): self
    {
        return new self($student, ContactStatus::for($student));
    }

    public function matchesCard(string $card): bool
    {
        return match ($card) {
            'due' => $this->status->isDue(),
            'high_overdue' => $this->student->risk_flag === Risk::High
                && ($this->status->neverContacted() || $this->status->overdue()),
            'waiting' => $this->status->waiting,
            'never' => $this->status->neverContacted(),
            'recent' => $this->status->contactedRecently(),
        };
    }

    /**
     * Case-insensitive match on name (as "First Last" or "Last, First"),
     * student ID, email, or major: what the search box says it searches.
     */
    public function matchesSearch(string $query): bool
    {
        $student = $this->student;
        $query = Str::trim($query);

        return collect([
            "{$student->first_name} {$student->last_name}",
            "{$student->last_name}, {$student->first_name}",
            $student->sis_id,
            $student->email,
            $student->major,
        ])->contains(fn (string $field) => Str::contains($field, $query, ignoreCase: true));
    }
}
