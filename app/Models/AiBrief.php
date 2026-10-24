<?php

namespace App\Models;

use App\Models\Concerns\UsesDatasetClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The cached prep brief for one student. student_id is the key, so
 * each student has at most one.
 */
class AiBrief extends Model
{
    use UsesDatasetClock;

    protected $primaryKey = 'student_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['content', 'notes_version'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
