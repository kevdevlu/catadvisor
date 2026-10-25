<?php

namespace App\Providers;

use App\Ai\ClaudeJsonModel;
use App\Ai\JsonModel;
use App\Models\Advisor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        // Only the admin may put the demo data back to the original dataset.
        Gate::define('reset-demo', fn (Advisor $user) => $user->isAdmin());
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

        // The AI routes cost money per call. The note check runs once per
        // saved note, so 10 a minute is more than an advisor types. A brief
        // GET usually hits the cache, so it allows more.
        RateLimiter::for('ai-check', fn (Request $request) => Limit::perMinute(10)->by($request->user()->id));
        RateLimiter::for('ai-brief', fn (Request $request) => Limit::perMinute(60)->by($request->user()->id));

        // A reset reloads the whole dataset; a few a minute is plenty.
        RateLimiter::for('demo-reset', fn (Request $request) => Limit::perMinute(3)->by($request->user()->id));
    }
}
