import { Link, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { track } from '@/lib/tracking';

export type NudgePayload = {
    slug: string;
    title: string;
    body: string;
    cta: string;
    url: string;
};

/**
 * Lifecycle nudge banner driven by the shared `nudge` Inertia prop
 * (wizard resume, plan ready, complete payment). Dismissal is beaconed as
 * `nudge.dismissed` so the server suppresses the nudge for its cooldown
 * window.
 */
export function NudgeBanner() {
    const { nudge } = usePage<{ nudge?: NudgePayload | null }>().props;
    const [hidden, setHidden] = useState(false);

    if (!nudge || hidden) {
        return null;
    }

    return (
        <div className="mb-4 flex items-start gap-3 rounded-xl border border-lime-500/30 bg-lime-500/10 p-4">
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">{nudge.title}</p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {nudge.body}
                </p>
                <Link
                    href={nudge.url}
                    className="mt-2 inline-flex rounded-md bg-lime-500 px-3 py-1.5 text-xs font-semibold text-lime-950 hover:bg-lime-400"
                >
                    {nudge.cta}
                </Link>
            </div>
            <button
                type="button"
                aria-label="Dismiss"
                onClick={() => {
                    track('nudge.dismissed', { nudge: nudge.slug });
                    setHidden(true);
                }}
                className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
