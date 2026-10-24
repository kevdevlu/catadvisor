<?php

namespace App\Providers;

use App\Ai\ClaudeJsonModel;
use App\Ai\JsonModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The AI model behind the prep brief and the note check. Tests bind a
        // fake instead.
        $this->app->bind(JsonModel::class, ClaudeJsonModel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineRateLimits();
    }

    /**
     * Rate limits. login allows 5 tries a minute for each account
     * from each address, and 20 a minute from one address across all
     * accounts, which stops password guessing. api allows 120 requests a
     * minute per person, plenty for one advisor clicking around.
     */
    private function defineRateLimits(): void
    {
        RateLimiter::for('login', function (Request $request) {
            // This runs before validation, so the username may be missing or
            // not text (for example a JSON array). Treat that as "" here and
            // let validation answer with the normal 422.
            $username = $request->input('username');
            $account = is_string($username) ? Str::lower($username) : '';

            return [
                Limit::perMinute(5)->by($account.'|'.$request->ip()),
                Limit::perMinute(20)->by('ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
