export class RequestError extends Error {
    constructor(
        message: string,
        readonly status: number,
    ) {
        super(message);
    }
}

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

/** JSON request against the Laravel app; throws RequestError with the first validation message. */
export async function sendJson<T>(
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    url: string,
    body?: Record<string, unknown> | FormData,
): Promise<T> {
    const init: RequestInit = {
        method,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(body && !(body instanceof FormData)
                ? { 'Content-Type': 'application/json' }
                : {}),
        },
    };
    if (body)
        init.body = body instanceof FormData ? body : JSON.stringify(body);

    let response: Response;
    try {
        response = await fetch(url, init);
    } catch {
        throw new RequestError(
            'You appear to be offline. The change was not saved.',
            0,
        );
    }

    if (response.ok) return (await response.json()) as T;

    let message = 'Something went wrong. The change was not saved.';
    if (response.status === 403)
        message = 'You are not allowed to change this.';
    if (response.status === 419)
        message = 'Your session expired. Reload the page.';
    if (response.status === 422) {
        const payload = (await response.json().catch(() => null)) as {
            message?: string;
            errors?: Record<string, string[]>;
        } | null;
        message =
            Object.values(payload?.errors ?? {})[0]?.[0] ??
            payload?.message ??
            message;
    }
    throw new RequestError(message, response.status);
}
