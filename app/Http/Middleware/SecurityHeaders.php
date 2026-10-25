<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Four security headers on every response, plus one for search engines.
 * Each security header tells the browser to lock one thing down, and none
 * of them can break the page:
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
 * X-Robots-Tag keeps the app out of search results: it is a prototype with
 * fictional data, shared by link. Laravel Cloud has a setting for this
 * header, but on a laravel.cloud address it isn't sent, so the app sends it.
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
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE);
        }

        return $response;
    }
}
