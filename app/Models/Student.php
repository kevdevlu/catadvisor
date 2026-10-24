<?php

namespace App\Models;

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
     * Remove any SSN before the preference is stored.
     */
    protected function contactPreference(): Attribute
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

    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class);
    }

    public function possibleDuplicate(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'possible_duplicate_of');
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
