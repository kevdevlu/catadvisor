<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->pinToday();
    }

    /**
     * Pin the date to APP_TODAY (2026-10-05 for this dataset) but keep the
     * real time of day, so now() returns Oct 5 at the current clock time.
     * Rationale: date math has to see Oct 5, but the clock still has to move.
     * With a fully frozen clock, rate limits and sessions never run out, so
     * one login lockout would last forever.
     */
    private function pinToday(): void
    {
        $today = config('catadvisor.today');

        if (blank($today)) {
            return;
        }

        $timezone = new DateTimeZone(config('app.timezone'));
        $date = CarbonImmutable::parse($today, $timezone);

        // Carbon calls this closure for every now(). It reads the real clock
        // with DateTimeImmutable, because calling Carbon here would loop.
        Carbon::setTestNow(function () use ($date, $timezone) {
            $clock = new DateTimeImmutable('now', $timezone);

            return $date->setTime(
                (int) $clock->format('H'),
                (int) $clock->format('i'),
                (int) $clock->format('s'),
                (int) $clock->format('u'),
            );
        });
    }
}
