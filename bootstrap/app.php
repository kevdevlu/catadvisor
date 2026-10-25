<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Let /api requests from the React app use the browser's session
        // cookie, with the same CSRF check as web forms (Sanctum's SPA mode).
        // Sanctum only does this for hosts listed in
        // config/sanctum.php "stateful", which includes the request's own host.
        $middleware->statefulApi();

        // Laravel Cloud puts a load balancer in front of the app. Trust the
        // X-Forwarded-* headers it adds, so Laravel sees the visitor's real
        // address (the login rate limit counts per address) and knows the
        // page was loaded over HTTPS (secure cookies, https:// redirects).
        // "*" is the usual setting when the balancers' addresses aren't fixed.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        // On every response, pages and API alike.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
