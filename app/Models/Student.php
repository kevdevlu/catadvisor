<?php

namespace App\Models;

use App\Enums\ContactType;
use App\Enums\Risk;
use App\Models\Concerns\UsesDatasetClock;
use App\Support\Ssn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasUuids;
    use UsesDatasetClock;

    /**
     * Only the fields an advisor edits by hand. Who set them, and the
     * student's own record (name, advisor, risk), are set by the code.
     */
    protected $fillable = ['contact_preference', 'follow_up_on', 'follow_up_reason'];

    /**
     * Remove any SSN before the preference or the follow-up reason is
     * stored. Both are free text that everyone who can see the
     * student reads.
     */
    protected function contactPreference(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : Ssn::redact($value));
    }

    protected function followUpReason(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : Ssn::redact($value));
    }

    protected function casts(): array
    {
        return [
            'gpa' => 'decimal:2',
            'risk_flag' => Risk::class,
            'contact_preference_set_at' => 'datetime',
            'follow_up_on' => 'date',
            'follow_up_set_at' => 'datetime',
            'awaiting_reply_since' => 'date',
        ];
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class);
    }

    /**
     * chaperone() hands each loaded note this student as its "student", so
     * checks like $note->student don't run one more query per note.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class)->chaperone();
    }

    public function possibleDuplicate(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'possible_duplicate_of');
    }

    /*
     * The notes and advisors behind the contact preference, the follow-up,
     * and "waiting on a reply". Source notes include
     * deleted ones, so the student view can still name a deleted note, as
     * plain text instead of a link.
     */

    public function contactPreferenceNote(): BelongsTo
    {
        return $this->belongsTo(ContactNote::class, 'contact_preference_note_id')->withTrashed();
    }

    public function contactPreferenceSetBy(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'contact_preference_set_by');
    }

    public function followUpNote(): BelongsTo
    {
        return $this->belongsTo(ContactNote::class, 'follow_up_note_id')->withTrashed();
    }

    public function followUpSetBy(): BelongsTo
    {
        return $this->belongsTo(Advisor::class, 'follow_up_set_by');
    }

    public function awaitingReplyNote(): BelongsTo
    {
        return $this->belongsTo(ContactNote::class, 'awaiting_reply_note_id')->withTrashed();
    }

    /**
     * If a new note answers the outreach the student never replied to, clear
     * "waiting on a reply" and return true. Zoom and in person
     * count as an answer, because the student was there; email and "Note
     * only" don't. The note must be dated on or after the unanswered
     * outreach. Rationale: an advisor adding last month's meeting today
     * shouldn't clear a reply that is still missing.
     *
     * A phone note may be a missed call, for example N018 "Left voicemail.
     * No response." So a phone note the AI may read is left to the AI check
     * that runs after the save. A private phone note is never sent to the
     * AI, so it counts as an answer right away.
     */
    public function clearWaitingIfAnswered(ContactNote $note): bool
    {
        $type = $note->contact_type;

        $answered = $this->awaiting_reply_since !== null
            && $type->isTwoWay()
            && ($type !== ContactType::Phone || $note->is_private)
            && $note->contact_date->gte($this->awaiting_reply_since);

        if ($answered) {
            $this->forceFill(['awaiting_reply_since' => null, 'awaiting_reply_note_id' => null])->save();
        }

        return $answered;
    }

    /**
     * A note was just made private. A contact preference the advisor
     * accepted from it stays, since the advisor confirmed it, but the link
     * to the note goes, so the page says "Set by <advisor> on <date>"
     * instead.
     *
     * A follow-up or "waiting on a reply" the AI already set from the note
     * stays, linked to it. Rationale: losing a follow-up
     * about, say, money trouble hurts the student more than keeping it.
     * Others see its date and "(private)" instead of the reason, and
     * the AI leaves it alone from then on.
     */
    public function unlinkPreferenceFrom(ContactNote $note): void
    {
        if ($this->contact_preference_note_id === $note->id) {
            $this->forceFill(['contact_preference_note_id' => null])->save();
        }
    }

    public function brief(): HasOne
    {
        return $this->hasOne(AiBrief::class);
    }

    /**
     * Limit a query to the students this person may see: their own, or all
     * of them for the admin.
     */
    public function scopeVisibleTo(Builder $query, Advisor $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('advisor_id', $user->id);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
