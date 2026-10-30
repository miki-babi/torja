declare global {
    interface Window {
        Telegram?: { WebApp?: { initData?: string } };
    }
}

/**
 * The signed `initData` Telegram injects when the app is opened inside a
 * Mini App — from the SDK's `Telegram.WebApp.initData` when it is loaded,
 * or the `tgWebAppData` param Telegram appends to the Mini App URL hash.
 * Returns the raw query string to verify server-side, or null in a normal
 * browser.
 */
export function telegramInitData(): string | null {
    const fromSdk = window.Telegram?.WebApp?.initData;

    if (typeof fromSdk === 'string' && fromSdk !== '') {
        return fromSdk;
    }

    const fromHash = new URLSearchParams(
        window.location.hash.slice(1),
    ).get('tgWebAppData');

    return fromHash !== null && fromHash !== '' ? fromHash : null;
}
