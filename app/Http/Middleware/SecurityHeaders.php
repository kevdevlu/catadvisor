<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Four security headers on every response. Each tells the browser
 * to lock one thing down, and none of them can break the page:
 *
 * - X-Frame-Options: no other site may show the app inside a frame, which
 *   stops a trick where a hidden frame gets someone to click "Save note".
 * - X-Content-Type-Options: the browser uses the file type the server sends
 *   and never guesses one.
 * - Referrer-Policy: following a link to another site doesn't tell it which
 *   page it came from. Page addresses hold student IDs.
 * - Strict-Transport-Security (HSTS): the browser uses HTTPS for this site
 *   from now on. Sent only in production over HTTPS. Rationale: on a local
 *   machine it would stick to the hostname for a year.
 *
 * A script allow-list (Content-Security-Policy) is out of scope.
 */
class SecurityHeaders
{
    // One year, in seconds.
    private const HSTS_MAX_AGE = 31536000;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE);
        }

        return $response;
    }
}
