<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdvisorResource;
use App\Models\Advisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Uri;
use Illuminate\Validation\ValidationException;

/**
 * The demo login: pick an account, type the shared password. A
 * real version would use NetID sign-in and store no passwords.
 */
class AuthController extends Controller
{
    /**
     * The accounts for the login page's picker, advisors first, then the
     * admin. Usernames are not secret here: they are the dataset's advisors.
     */
    public function accounts(): JsonResponse
    {
        $accounts = Advisor::all()
            ->sortBy(fn (Advisor $advisor) => [$advisor->isAdmin(), $advisor->display_name])
            ->values();

        return AdvisorResource::collection($accounts)->response();
    }

    public function login(Request $request): AdvisorResource
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // The same message for a wrong account or a wrong password, so the
        // reply never tells a guesser which part was right.
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'password' => 'That password is not right. Try again.',
            ]);
        }

        // Give the session a new ID now that it is signed in. Rationale: an
        // ID someone planted before sign-in (session fixation) stops working.
        $request->session()->regenerate();

        return (new AdvisorResource($request->user()))
            ->additional(['meta' => ['intended' => $this->intendedPath($request)]]);
    }

    /**
     * The page a signed-out visitor first asked for, for example
     * /students/9b1f0c2e-... Laravel remembers it when it sends them to
     * /login. Only the path is returned, so it always stays on this site.
     */
    private function intendedPath(Request $request): string
    {
        $uri = Uri::of($request->session()->pull('url.intended', '/'));

        // Uri::path() leaves out the leading slash, and returns "/" for none.
        $path = $uri->path() === '/' ? '/' : '/'.$uri->path();
        $query = $uri->query()->value();

        return $query !== '' ? "{$path}?{$query}" : $path;
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): AdvisorResource
    {
        return new AdvisorResource($request->user());
    }
}
