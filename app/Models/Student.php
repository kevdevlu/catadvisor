<?php

namespace App\Models;

use App\Enums\Risk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

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

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
