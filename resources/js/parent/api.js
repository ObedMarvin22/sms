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
 * Downloads a PDF from /api/v1 with the parent's token and hands it to the phone to save or open.
 * (A plain link cannot send the token.) The file is not kept by the app.
 */
export async function download(path, fallbackName) {
    let response;
    try {
        response = await fetch(`/api/v1/${path.replace(/^\//, '')}`, {
            headers: { Accept: 'application/pdf', Authorization: `Bearer ${session.token}` },
        });
    } catch {
        throw new ApiError(0, 'No connection. Check your data or Wi-Fi and try again.');
    }

    if (response.status === 401) {
        forget();
        onSignedOut();
    }
    if (!response.ok) {
        throw new ApiError(response.status, response.status === 404 ? 'Not found.' : 'The document could not be downloaded. Please try again.');
    }

    const name = /filename="?([^";]+)"?/.exec(response.headers.get('Content-Disposition') ?? '')?.[1] ?? fallbackName;
    const url = URL.createObjectURL(await response.blob());
    const link = Object.assign(document.createElement('a'), { href: url, download: name });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 30_000);
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
