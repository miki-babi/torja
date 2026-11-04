import { Loader2, MapPin } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { locationSupported, shareLocation } from '@/lib/location';
import { useTranslation } from '@/lib/i18n';

/**
 * Shared shape and label helper for "People you may know" suggestions.
 * The server scores each candidate from the social graph and sends a
 * reason key plus any interpolation values; the UI maps it to the
 * localized member.ranks.suggest.* lines.
 */
export type Suggested = {
    id: number;
    name: string;
    avatar: string | null;
    reason?: {
        key: string;
        name?: string;
        count?: number;
    };
};

export function reasonText(
    t: (key: string, replacements?: Record<string, string | number>) => string,
    reason: Suggested['reason'],
): string | null {
    if (reason === undefined) {
        return null;
    }

    switch (reason.key) {
        case 'mutual_friends':
            return t('member.ranks.suggest.mutual_friends', {
                count: reason.count ?? 1,
            });
        case 'gym':
            return t('member.ranks.suggest.gym', { name: reason.name ?? '' });
        case 'group':
            return t('member.ranks.suggest.group', { name: reason.name ?? '' });
        case 'nearby':
            return t('member.ranks.suggest.nearby', {
                name: reason.name ?? '',
            });
        default:
            return t('member.ranks.suggest.new');
    }
}

/**
 * Prompts the location share (Telegram LocationManager in the Mini App,
 * browser geolocation elsewhere) and persists the coordinates via
 * POST /member/location. `onShared` fires after a successful save so the
 * caller can reload suggestions.
 */
export function ShareLocationButton({ onShared }: { onShared?: () => void }) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);

    if (!locationSupported()) {
        return null;
    }

    return (
        <Button
            size="sm"
            variant="outline"
            disabled={busy}
            onClick={async () => {
                setBusy(true);
                try {
                    const shared = await shareLocation();

                    if (shared) {
                        onShared?.();
                    } else {
                        toast.error(t('member.ranks.suggest.share_denied'));
                    }
                } catch {
                    toast.error(t('member.ranks.suggest.share_denied'));
                } finally {
                    setBusy(false);
                }
            }}
        >
            {busy ? (
                <Loader2 className="size-4 animate-spin" />
            ) : (
                <MapPin className="size-4" />
            )}
            {t('member.ranks.suggest.share_button')}
        </Button>
    );
}
