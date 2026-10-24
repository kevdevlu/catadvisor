<?php

namespace App\Models\Concerns;

use App\Support\Clock;
use Illuminate\Support\Carbon;

/**
 * Stamp created_at and updated_at with the dataset's "today" (Clock::now())
 * instead of the real date, so an edit made during the demo reads
 * "Edited Oct 5, 2026" like everything else in the data.
 */
trait UsesDatasetClock
{
    public function freshTimestamp(): Carbon
    {
        return Carbon::instance(Clock::now());
    }
}
