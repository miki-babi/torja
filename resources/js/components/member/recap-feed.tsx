import { Loader2, Users } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import { RecapCard } from '@/components/member/recap-card';
import type { Recap, RecapFeedPage } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { index as recapsIndex } from '@/routes/member/recaps';

/**
 * Community recap feed on the member home page: newest recap posts from all
 * members with bravo + inline comments, paged via the `before` cursor.
 */
export function RecapFeed({
    initialRecaps,
    initialNextCursor,
}: {
    initialRecaps: Recap[];
    initialNextCursor: number | null;
}) {
    const { t } = useTranslation();
    const [recaps, setRecaps] = useState<Recap[]>(initialRecaps);
    const [cursor, setCursor] = useState<number | null>(initialNextCursor);
    const [loading, setLoading] = useState(false);

    async function loadMore() {
        if (loading || cursor === null) {
            return;
        }
        setLoading(true);
        try {
            const page = await memberApi<RecapFeedPage>(
                recapsIndex({ query: { before: cursor } }).url,
                'GET',
            );
            setRecaps((list) => [
                ...list,
                ...page.recaps.filter(
                    (recap) => !list.some((l) => l.id === recap.id),
                ),
            ]);
            setCursor(page.next_cursor);
        } catch {
            toast.error(t('member.recap.publish_failed_toast'));
        } finally {
            setLoading(false);
        }
    }

    function removeRecap(recapId: number) {
        setRecaps((list) => list.filter((recap) => recap.id !== recapId));
    }

    return (
        <div>
            <div className="flex items-center gap-2">
                <Users className="size-4 text-[#AFFA01]" />
                <h2 className="text-sm font-bold tracking-wide text-[#F5F5F5] uppercase">
                    {t('member.home.feed_title')}
                </h2>
            </div>

            {recaps.length === 0 ? (
                <div className="mt-3 rounded-3xl border border-dashed border-[#3a3a3a] bg-[#1F1F1F] px-4 py-8 text-center">
                    <p className="mx-auto max-w-55 text-xs leading-relaxed text-[#8E8E93]">
                        {t('member.home.feed_empty')}
                    </p>
                </div>
            ) : (
                <div className="mt-3 space-y-4">
                    {recaps.map((recap) => (
                        <RecapCard
                            key={recap.id}
                            recap={recap}
                            onDeleted={removeRecap}
                        />
                    ))}
                </div>
            )}

            {cursor !== null && (
                <Button
                    type="button"
                    variant="outline"
                    onClick={loadMore}
                    disabled={loading}
                    className="mt-4 w-full border-[#2E2E2E] bg-[#1F1F1F] text-xs font-bold text-[#A1A1AA] hover:border-[#AFFA01]/40 hover:text-[#AFFA01]"
                >
                    {loading && <Loader2 className="size-3.5 animate-spin" />}
                    {t('member.home.feed_load_more')}
                </Button>
            )}
        </div>
    );
}
