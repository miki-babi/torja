/**
 * First-party + third-party tracking client.
 *
 * - `track()` buffers events and ships them to the `/t` beacon endpoint via
 *   `sendBeacon`/`fetch keepalive`, and forwards whitelisted events to any
 *   active ad pixels (Meta `fbq`) through each integration's event map.
 * - Integrations are delivered via the shared `tracking` Inertia prop; the
 *   browser only receives id/provider/external_id/event_map — never secrets.
 */

export type TrackingIntegration = {
    id: number;
    provider: string;
    name: string;
    external_id: string;
    event_map: Record<string, string>;
    consent_required: boolean;
    snippet?: string | null;
};

type QueuedEvent = { name: string; properties?: Record<string, unknown> };

const STANDARD_META_EVENTS = new Set([
    'AddPaymentInfo',
    'AddToCart',
    'AddToWishlist',
    'CompleteRegistration',
    'Contact',
    'CustomizeProduct',
    'Donate',
    'FindLocation',
    'InitiateCheckout',
    'Lead',
    'PageView',
    'Purchase',
    'Schedule',
    'Search',
    'StartTrial',
    'SubmitApplication',
    'Subscribe',
    'ViewContent',
]);

declare global {
    interface Window {
        fbq?: Fbq & { queue?: unknown[] };
        _fbq?: Fbq;
    }
}

type Fbq = (...args: unknown[]) => void;

let active: TrackingIntegration[] = [];
let queue: QueuedEvent[] = [];
let flushing = false;

/**
 * Keys of raw snippets already injected this session — initIntegration runs
 * on every navigation, but a pasted snippet must only execute once per
 * unique payload.
 */
const injectedSnippets = new Set<string>();

/**
 * Replace the active integration list (called on boot and each navigation).
 */
export function setActiveIntegrations(integrations: TrackingIntegration[]) {
    active = integrations;
}

function consentSatisfied(integration: TrackingIntegration): boolean {
    if (!integration.consent_required) {
        return true;
    }

    return (
        typeof document !== 'undefined' &&
        document.cookie.includes('tp_consent=1')
    );
}

function ensureFbq(): Fbq | null {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return null;
    }

    if (window.fbq) {
        return window.fbq;
    }

    const fbq = function (...args: unknown[]) {
        (fbq.queue ??= []).push(args);
    } as Fbq & { queue?: unknown[] };

    fbq.queue = [];
    window.fbq = fbq;
    window._fbq = fbq;

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://connect.facebook.net/en_US/fbevents.js';
    const first = document.getElementsByTagName('script')[0];
    first?.parentNode?.insertBefore(script, first);

    return fbq;
}

/**
 * Parse a pasted provider snippet and append it to <head>, replacing each
 * <script> node with a freshly created element so it actually executes
 * (fragment-inserted scripts are inert). For Meta snippets the embedded
 * `fbq('track', 'PageView')` is stripped first — `firePageView()` stays the
 * single PageView source on every navigation.
 */
function injectSnippet(html: string, { stripPageView = false } = {}) {
    if (typeof document === 'undefined') {
        return;
    }

    const source = stripPageView
        ? html.replace(
              /fbq\(\s*['"]track['"]\s*,\s*['"]PageView['"]\s*\)\s*;?/g,
              '',
          )
        : html;

    const fragment = document.createRange().createContextualFragment(source);

    fragment.querySelectorAll('script').forEach((original) => {
        const script = document.createElement('script');

        for (const attribute of original.attributes) {
            script.setAttribute(attribute.name, attribute.value);
        }

        script.text = original.textContent ?? '';
        original.replaceWith(script);
    });

    document.head.appendChild(fragment);
}

/**
 * Inject the provider script and initialise the pixel for one integration.
 * An admin-pasted raw snippet takes precedence and is injected verbatim;
 * otherwise Meta integrations fall back to the built-in fbq loader.
 */
export function initIntegration(integration: TrackingIntegration) {
    if (!consentSatisfied(integration)) {
        return;
    }

    if (integration.snippet) {
        const key = `${integration.id}:${integration.snippet}`;

        if (!injectedSnippets.has(key)) {
            injectedSnippets.add(key);
            injectSnippet(integration.snippet, {
                stripPageView: integration.provider === 'meta',
            });
        }

        return;
    }

    if (integration.provider !== 'meta') {
        return;
    }

    const fbq = ensureFbq();

    if (fbq) {
        fbq('init', integration.external_id);
    }
}

/**
 * Fire PageView on every Meta pixel that is currently active.
 */
export function firePageView() {
    const fbq = window.fbq;

    if (!fbq) {
        return;
    }

    for (const integration of active) {
        if (integration.provider === 'meta' && consentSatisfied(integration)) {
            fbq('track', 'PageView');
        }
    }
}

function flush() {
    if (flushing || queue.length === 0 || typeof navigator === 'undefined') {
        return;
    }

    flushing = true;
    const batch = queue;
    queue = [];
    const body = JSON.stringify({ events: batch.slice(0, 20) });

    const sent = navigator.sendBeacon?.(
        '/t',
        new Blob([body], { type: 'application/json' }),
    );

    if (!sent) {
        void fetch('/t', {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body,
        }).catch(() => undefined);
    }

    flushing = false;
}

/**
 * Record an interaction: beacons it first-party and forwards it to any
 * integration that maps the event to a provider event.
 */
export function track(name: string, properties: Record<string, unknown> = {}) {
    queue.push({ name, properties });
    scheduleFlush();

    const fbq = window.fbq;

    if (!fbq) {
        return;
    }

    for (const integration of active) {
        const mapped = integration.event_map?.[name];

        if (
            integration.provider === 'meta' &&
            mapped &&
            consentSatisfied(integration)
        ) {
            fbq(
                STANDARD_META_EVENTS.has(mapped) ? 'track' : 'trackCustom',
                mapped,
                properties,
            );
        }
    }
}

let timer: ReturnType<typeof setTimeout> | null = null;

function scheduleFlush() {
    if (typeof window === 'undefined') {
        return;
    }

    if (timer === null) {
        timer = setTimeout(() => {
            timer = null;
            flush();
        }, 2500);
    }
}

if (typeof window !== 'undefined') {
    window.addEventListener('pagehide', flush);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flush();
        }
    });
}
