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
}
