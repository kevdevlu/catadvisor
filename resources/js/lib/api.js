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

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export async function api(path, { method = 'GET', body } = {}) {
    const response = await fetch(path, {
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

    const data = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(response.status, data);
    }

    return data;
}
