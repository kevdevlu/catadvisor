<?php

namespace App\Models;

use App\Enums\ContactType;
use App\Models\Concerns\UsesDatasetClock;
use App\Support\Ssn;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One note on a student: a contact (phone, email, Zoom, in person) or a
 * "Note only" entry. Deleting a note only sets deleted_at, so the
 * record is kept.
 */
class ContactNote extends Model
{
    use HasUuids;
    use SoftDeletes;
    use UsesDatasetClock;

    /**
     * Only the fields an advisor fills in on the note form. The student,
     * author, and audit fields are set by the code from the login
     * (a note's author never comes from a form field).
     */
    protected $fillable = ['contact_date', 'contact_type', 'note_text', 'is_private'];

    /**
     * Remove any SSN before the text is stored.
     */
    protected function noteText(): Attribute
    {
        return Attribute::make(set: fn (string $value) => Ssn::redact($value));
    }

    protected function casts(): array
    {
        return [
            'contact_date' => 'date',
            'contact_type' => ContactType::class,
            'is_private' => 'boolean',
            'ai_read' => 'boolean',
            'revision' => 'integer',
            'ai_checked_revision' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'author_id');
    }

    /**
     * Whether the AI has already checked this version of the note. An edit
     * makes it due for a new check.
     */
    public function checkedSinceLastChange(): bool
    {
        return $this->ai_checked_revision === $this->revision;
    }

    /**
     * Sorts notes by date, newest last; use with sortByDesc() for newest
     * first. Notes on the same date go by when they were saved. created_at
     * only keeps whole seconds, so the id breaks a tie: Laravel's UUIDs
     * (version 7) start with the time they were made, to the millisecond.
     */
    public function sortKey(): string
    {
        return "{$this->contact_date->toDateString()} {$this->created_at} {$this->id}";
    }

    /**
     * How the student view names this note when something points back to
     * it, for example "Jun 2 email note", or "Jan 22, 2026 phone note" with
     * the year. A "Note only" entry is just "Sep 11 note".
     */
    public function sourceName(bool $withYear = false): string
    {
        $date = $this->contact_date->format($withYear ? 'M j, Y' : 'M j');

        $type = match ($this->contact_type) {
            ContactType::Zoom => 'Zoom',
            ContactType::InPerson => 'in-person',
            ContactType::NoteOnly => '',
            default => Str::lower($this->contact_type->label()),
        };

        return Str::squish("{$date} {$type} note");
    }
}
