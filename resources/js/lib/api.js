// Calls the Laravel API and the sign-in routes on this same site. The browser
// sends the session cookie by itself. The X-XSRF-TOKEN header copies Laravel's
// XSRF-TOKEN cookie, which is how Laravel knows a request came from this app
// and not from another site (CSRF protection).

export class ApiError extends Error {
    constructor(status, body) {
        super(body?.message ?? `Request failed with status ${status}`);
        this.status = status;
        this.body = body;
    }
}

// Set by AuthProvider: called when the server says nobody is signed in, so
// the app can go back to the login page.
let onUnauthorized = () => {};

export function setUnauthorizedHandler(handler) {
    onUnauthorized = handler;
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function send(path, method, body) {
    return fetch(path, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(body !== undefined && { 'Content-Type': 'application/json' }),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
}

// The message to show for a failed request: the first validation error from
// Laravel (a 422 answer), or the fallback for anything else.
export function errorMessage(error, fallback) {
    const errors = error?.body?.errors;
    return errors ? Object.values(errors)[0][0] : fallback;
}

export async function api(path, { method = 'GET', body } = {}) {
    let response = await send(path, method, body);

    // 419 means the server no longer knows this page's CSRF token, for example
    // after the session expired or the demo data was reset. Ask for a fresh
    // token once, then retry. Without this, every retry fails the same way.
    if (response.status === 419) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
        response = await send(path, method, body);
    }

    if (response.status === 401) {
        onUnauthorized();
    }

    if (response.status === 204) {
        return null;
    }

    // Anything that isn't JSON (for example a redirect that landed on the app's
    // HTML page) is treated as a failure rather than read as data.
    const isJson = response.headers.get('Content-Type')?.includes('application/json');
    const data = isJson ? await response.json().catch(() => null) : null;

    if (!response.ok || !isJson) {
        throw new ApiError(response.status, data);
    }

    return data;
}
