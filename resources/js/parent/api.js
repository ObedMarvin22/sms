import { forget, session } from './session.js';

/**
 * An error from the API. `errors` holds Laravel's field messages ({ phone: ['…'] }) on a 422.
 */
export class ApiError extends Error {
    constructor(status, message, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }

    /** The first message for a field, for showing under it. */
    field(name) {
        return this.errors[name]?.[0] ?? null;
    }
}

let onSignedOut = () => {};

/** The router sets this: where to go when the token is refused. */
export function whenSignedOut(callback) {
    onSignedOut = callback;
}

/**
 * Calls /api/v1 with the parent's token. Resolves to the JSON body (null for 204).
 */
export async function api(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    if (session.token) {
        headers.Authorization = `Bearer ${session.token}`;
    }

    let response;
    try {
        response = await fetch(`/api/v1/${path.replace(/^\//, '')}`, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError(0, 'No connection. Check your data or Wi-Fi and try again.');
    }

    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => ({}));

    if (response.status === 401 && session.token) {
        forget();
        onSignedOut();
    }

    if (!response.ok) {
        const message =
            response.status === 429
                ? 'Too many tries. Please wait a minute and try again.'
                : response.status === 404
                  ? 'Not found.'
                  : (data.message ?? 'Something went wrong. Please try again.');

        throw new ApiError(response.status, message, data.errors ?? {});
    }

    return data;
}
