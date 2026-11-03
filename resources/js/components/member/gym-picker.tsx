import { Check, Loader2, Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as gymsIndex } from '@/routes/member/ranks/gyms';
import {
    destroy as gymDestroy,
    store as gymStore,
} from '@/routes/member/ranks/gym';

export type GymPick = { id: number; name: string };
type GymResult = GymPick & { members: number };

/**
 * Search-or-create gym picker: type to search the shared list, pick a
 * gym to join it (chat seat included), or create a new one. The current
 * pick can be left entirely.
 */
export function GymPicker({
    gym,
    onPicked,
    onLeft,
}: {
    gym: GymPick | null;
    onPicked: (gym: GymPick, chatId: number) => void;
    onLeft: () => void;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GymResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [saving, setSaving] = useState(false);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (debounce.current !== null) {
            clearTimeout(debounce.current);
        }
        debounce.current = setTimeout(async () => {
            setSearching(true);
            try {
                const { gyms } = await memberApi<{ gyms: GymResult[] }>(
                    gymsIndex({ query: { q: query.trim() } }).url,
                    'GET',
                );
                setResults(gyms);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 250);

        return () => {
            if (debounce.current !== null) {
                clearTimeout(debounce.current);
            }
        };
    }, [query]);

    async function pick(gymId?: number, name?: string) {
        setSaving(true);
        try {
            const { gym: picked, chat_id } = await memberApi<{
                gym: GymPick;
                chat_id: number;
            }>(
                gymStore().url,
                'POST',
                gymId !== undefined ? { gym_id: gymId } : { name: name ?? '' },
            );
            setQuery('');
            onPicked(picked, chat_id);
        } catch {
            toast.error(t('member.ranks.gym_error'));
        } finally {
            setSaving(false);
        }
    }

    async function leave() {
        setSaving(true);
        try {
            await memberApi(gymDestroy().url, 'DELETE');
            onLeft();
        } finally {
            setSaving(false);
        }
    }

    const exact = results.some(
        (r) => r.name.toLowerCase() === query.trim().toLowerCase(),
    );
    const canCreate = query.trim().length >= 2 && !exact;

    return (
        <div className="space-y-3">
            {gym !== null && (
                <div className="flex items-center justify-between rounded-xl border border-[#AFFA01]/30 bg-[#AFFA01]/5 px-3 py-2">
                    <div className="flex items-center gap-2">
                        <Check className="size-4 text-[#AFFA01]" />
                        <span className="text-sm font-bold text-[#F5F5F5]">
                            {gym.name}
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={leave}
                        disabled={saving}
                        aria-label={t('member.ranks.gym_leave')}
                        className="p-1 text-[#8E8E93] hover:text-[#FF6B6B]"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            )}

            <div className="relative">
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#8E8E93]" />
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={t('member.ranks.gym_placeholder')}
                    className="pl-9"
                />
                {searching && (
                    <Loader2 className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-[#8E8E93]" />
                )}
            </div>

            {(results.length > 0 || canCreate) && query.trim() !== '' && (
                <div className="divide-y divide-[#2E2E2E] overflow-hidden rounded-xl border border-[#2E2E2E] bg-[#101010]">
                    {results.map((r) => (
                        <button
                            key={r.id}
                            type="button"
                            disabled={saving}
                            onClick={() => pick(r.id)}
                            className={cn(
                                'flex w-full items-center justify-between px-3 py-2.5 text-left text-sm',
                                gym?.id === r.id
                                    ? 'text-[#AFFA01]'
                                    : 'text-[#F5F5F5] hover:bg-[#161616]',
                            )}
                        >
                            <span className="truncate font-semibold">
                                {r.name}
                            </span>
                            <span className="shrink-0 text-[10px] text-[#8E8E93]">
                                {r.members} {t('member.ranks.gym_members')}
                            </span>
                        </button>
                    ))}
                    {canCreate && (
                        <Button
                            variant="ghost"
                            disabled={saving}
                            onClick={() => pick(undefined, query.trim())}
                            className="h-auto w-full justify-start rounded-none px-3 py-2.5 text-[#AFFA01]"
                        >
                            <Plus className="size-4" />
                            {t('member.ranks.gym_create', {
                                name: query.trim(),
                            })}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
