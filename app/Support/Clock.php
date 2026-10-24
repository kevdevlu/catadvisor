<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * "Now" for student data. The dataset is written as of 2026-10-05
 * (APP_TODAY), so days since contact and due dates count from that date,
 * and new notes and student changes are stamped with it (see the
 * UsesDatasetClock trait). Leave APP_TODAY empty to use the real date.
 *
 * Everything else (sessions, cookies, rate limits) keeps the real clock.
 * Rationale: a session cookie stamped "Oct 5 plus 14 days" has already
 * expired in real time on Oct 24, so the browser throws it away and nobody
 * stays signed in.
 */
final class Clock
{
    /**
     * Midnight on "today".
     */
    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /**
     * "Today" at the real time of day, so stamps made one after another
     * still come out in order.
     */
    public static function now(): CarbonImmutable
    {
        $real = now()->toImmutable();
        $today = config('catadvisor.today');

        if (blank($today)) {
            return $real;
        }

        return CarbonImmutable::parse($today, $real->timezone)->setTimeFrom($real);
    }
}
