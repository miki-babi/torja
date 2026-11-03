import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { xsrfToken } from '@/components/member/member-api';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

window.Pusher = Pusher;

let instance: Echo<'reverb'> | null = null;

/**
 * Lazy Reverb websocket client. Created on first use so guest pages never
 * open a socket; the XSRF header is read per connect attempt.
 */
export function echo(): Echo<'reverb'> {
    instance ??= new Echo<'reverb'>({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    return instance;
}
