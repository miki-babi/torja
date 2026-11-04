import { memberApi } from '@/components/member/member-api';
import { store as locationStore } from '@/routes/member/location';

export type SharedLocation = { latitude: number; longitude: number };

/**
 * Resolve the member's current coordinates — the Telegram
 * `LocationManager` inside a Mini App (v8.0+, behind its own
 * permission prompt), or the browser's Geolocation API elsewhere.
 * Resolves null when neither is available or the user declines.
 */
export function requestLocation(): Promise<SharedLocation | null> {
    const manager = window.Telegram?.WebApp?.LocationManager;

    if (
        manager !== undefined &&
        window.Telegram?.WebApp?.isVersionAtLeast?.('8.0')
    ) {
        return new Promise((resolve) => {
            const ask = () =>
                manager.getLocation?.((location) =>
                    resolve(
                        location === null
                            ? null
                            : {
                                  latitude: location.latitude,
                                  longitude: location.longitude,
                              },
                    ),
                );

            if (manager.isInited) {
                ask();
            } else {
                manager.init?.(ask);
            }
        });
    }

    if ('geolocation' in navigator) {
        return new Promise((resolve) =>
            navigator.geolocation.getCurrentPosition(
                (position) =>
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                    }),
                () => resolve(null),
                { timeout: 8000, maximumAge: 300_000 },
            ),
        );
    }

    return Promise.resolve(null);
}

/**
 * Whether any location source is plausibly available — drives the
 * share-location CTA visibility.
 */
export function locationSupported(): boolean {
    return (
        window.Telegram?.WebApp?.LocationManager !== undefined ||
        'geolocation' in navigator
    );
}

/**
 * Share the member's location with the server. Returns true when a
 * position was captured and persisted.
 */
export async function shareLocation(): Promise<boolean> {
    const location = await requestLocation();

    if (location === null) {
        return false;
    }

    await memberApi(locationStore().url, 'POST', location);

    return true;
}
