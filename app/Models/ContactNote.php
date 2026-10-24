<?php

namespace App\Models;

use App\Enums\ContactType;
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

    protected $guarded = ['id'];

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
