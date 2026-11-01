export function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));
    return decodeURIComponent(cookie?.split('=')[1] ?? '');
}

export async function memberApi<T>(
    url: string,
    method: 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    body?: unknown,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const data: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const message =
            typeof data === 'object' &&
            data !== null &&
            'message' in data &&
            typeof data.message === 'string'
                ? data.message
                : `Request failed: ${response.status}`;

        const error = new Error(message);
        (error as Error & { status?: number }).status = response.status;
        throw error;
    }

    return data as T;
}
