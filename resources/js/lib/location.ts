import { memberApi } from '@/components/member/member-api';
import type { TelegramLocationData } from '@/lib/telegram';
import { store as locationStore } from '@/routes/member/location';

export type SharedLocation = { latitude: number; longitude: number };

export type LocationResult =
    | { status: 'granted'; location: SharedLocation }
    | { status: 'denied' | 'unavailable' };

type LocationManager = NonNullable<
    NonNullable<
        NonNullable<NonNullable<Window['Telegram']>['WebApp']>
    >['LocationManager']
>;

/**
 * The Telegram `LocationManager` — the only location source, available
 * in Mini App clients v8.0+. Null in a normal browser and on older
 * Telegram clients.
 */
function telegramLocationManager(): LocationManager | null {
    const webApp = window.Telegram?.WebApp;

    if (
        webApp?.LocationManager === undefined ||
        webApp.isVersionAtLeast?.('8.0') !== true
    ) {
        return null;
    }

    return webApp.LocationManager;
}

/**
 * Whether the Telegram `LocationManager` can be used — drives the
 * share-location CTA visibility.
 */
export function locationSupported(): boolean {
    return telegramLocationManager() !== null;
}

/**
 * Bring the manager to `isInited`: `init()` fires `locationManagerUpdated`
 * and (when given one) its callback — resolve on whichever arrives first.
 */
function initLocationManager(manager: LocationManager): Promise<void> {
    if (manager.isInited || manager.init === undefined) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        let settled = false;
        const finish = () => {
            if (settled) {
                return;
            }
            settled = true;
            window.Telegram?.WebApp?.offEvent?.(
                'locationManagerUpdated',
                finish,
            );
            resolve();
        };

        window.Telegram?.WebApp?.onEvent?.('locationManagerUpdated', finish);
        manager.init?.(finish);
    });
}

/**
 * Request the member's coordinates through the Telegram `LocationManager`.
 * Resolves `granted` with the position, `denied` when the user refuses or
 * the request stalls (15s timeout), and `unavailable` when the device or
 * client cannot supply a location. A previously-refused access request
 * resolves `denied` without prompting again — recovery goes through
 * `openLocationSettings`.
 */
export async function requestLocation(): Promise<LocationResult> {
    const manager = telegramLocationManager();

    if (manager === null) {
        return { status: 'unavailable' };
    }

    await initLocationManager(manager);

    if (!manager.isLocationAvailable) {
        return { status: 'unavailable' };
    }

    if (manager.isAccessRequested && !manager.isAccessGranted) {
        return { status: 'denied' };
    }

    return new Promise((resolve) => {
        const granted = (data: TelegramLocationData): LocationResult => ({
            status: 'granted',
            location: {
                latitude: data.latitude,
                longitude: data.longitude,
            },
        });

        let settled = false;
        const settle = (result: LocationResult) => {
            if (settled) {
                return;
            }
            settled = true;
            clearTimeout(timer);
            window.Telegram?.WebApp?.offEvent?.(
                'locationRequested',
                onRequested,
            );
            resolve(result);
        };

        const onRequested = (payload?: unknown) => {
            const data = (
                payload as { locationData?: TelegramLocationData | null }
            )?.locationData;
            settle(
                data === null || data === undefined
                    ? { status: 'denied' }
                    : granted(data),
            );
        };

        const timer = setTimeout(() => settle({ status: 'denied' }), 15_000);

        window.Telegram?.WebApp?.onEvent?.('locationRequested', onRequested);
        manager.getLocation?.((location) =>
            settle(
                location === null ? { status: 'denied' } : granted(location),
            ),
        );
    });
}

/**
 * Request the coordinates and persist them via POST /member/location.
 * The result mirrors `requestLocation`; the POST only runs on `granted`
 * and network errors propagate to the caller.
 */
export async function shareLocation(): Promise<LocationResult> {
    const result = await requestLocation();

    if (result.status === 'granted') {
        await memberApi(locationStore().url, 'POST', result.location);
    }

    return result;
}

/**
 * Open Telegram's location settings — must be called from a user
 * gesture (e.g. a toast action) or the client ignores it.
 */
export function openLocationSettings(): void {
    telegramLocationManager()?.openSettings?.();
}
