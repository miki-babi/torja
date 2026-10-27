import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    firePageView,
    initIntegration,
    setActiveIntegrations,
    type TrackingIntegration,
} from '@/lib/tracking';

type TrackingProps = {
    tracking?: { integrations?: TrackingIntegration[] };
};

function readInitialIntegrations(): TrackingIntegration[] {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (!raw) {
            return [];
        }

        const page = JSON.parse(raw) as { props?: TrackingProps };

        return page.props?.tracking?.integrations ?? [];
    } catch {
        return [];
    }
}

/**
 * Mounts admin-configured tracking pixels (e.g. the Meta pixel) on every
 * page and fires PageView on each Inertia navigation. Reads the initial
 * integration list from the server-rendered `data-page` attribute — this
 * component lives outside the Inertia page context — and refreshes it on
 * every successful visit so toggles take effect without a reload.
 */
export function TrackingScripts() {
    useEffect(() => {
        const sync = (integrations: TrackingIntegration[]) => {
            setActiveIntegrations(integrations);
            integrations.forEach(initIntegration);
        };

        sync(readInitialIntegrations());
        firePageView();

        const off = router.on('navigate', (event) => {
            const props = event.detail.page?.props as TrackingProps | undefined;
            sync(props?.tracking?.integrations ?? []);
            firePageView();
        });

        return () => {
            off();
        };
    }, []);

    return null;
}
