import { xsrfToken } from '@/components/member/member-api';
import { debug, webapp } from '@/routes/telegram/login';

declare global {
    interface Window {
        Telegram?: {
            WebApp?: {
                initData?: string;
                ready?: () => void;
                expand?: () => void;
            };
        };
    }
}

/**
 * The signed `initData` Telegram injects when the app is opened inside a
 * Mini App — from the SDK's `Telegram.WebApp.initData` when it is loaded,
 * or the `tgWebAppData` param Telegram appends to the Mini App URL hash.
 * Returns the raw query string to verify server-side, or null in a normal
 * browser.
 */
export function telegramInitData(): {
    initData: string;
    source: 'sdk' | 'hash';
} | null {
    const fromSdk = window.Telegram?.WebApp?.initData;

    if (typeof fromSdk === 'string' && fromSdk !== '') {
        return { initData: fromSdk, source: 'sdk' };
    }

    const fromHash = hashParams().get('tgWebAppData');

    return fromHash !== null && fromHash !== ''
        ? { initData: fromHash, source: 'hash' }
        : null;
}

/**
 * Fire-and-forget client log sink — reports Mini App auth outcomes to the
 * server so sign-in attempts leave a trail in the Laravel log.
 */
export function reportTelegramDebug(
    event: string,
    context: Record<string, unknown> = {},
): void {
    fetch(debug().url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify({ event, context }),
    }).catch(() => {});
}

/**
 * Post verified Mini App initData to the sign-in endpoint. Keeps the raw
 * fetch (rather than memberApi) so the HTTP status survives for logging.
 */
export async function telegramSignIn(
    initData: string,
): Promise<{ redirect: string }> {
    const response = await fetch(webapp().url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify({ init_data: initData }),
    });

    if (!response.ok) {
        throw Object.assign(new Error('Telegram sign-in failed'), {
            status: response.status,
        });
    }

    return response.json() as Promise<{ redirect: string }>;
}

/**
 * `key=value` pairs from the URL hash, decoded Telegram's way
 * (`decodeURIComponent` per pair — `URLSearchParams` would also turn a
 * literal `+` into a space and corrupt the signed payload).
 */
function hashParams(): Map<string, string> {
    const params = new Map<string, string>();

    for (const pair of window.location.hash.slice(1).split('&')) {
        const index = pair.indexOf('=');

        if (index === -1) {
            continue;
        }

        try {
            params.set(
                decodeURIComponent(pair.slice(0, index)),
                decodeURIComponent(pair.slice(index + 1)),
            );
        } catch {
            // Keep malformed pairs out rather than aborting the whole parse.
        }
    }

    return params;
}
