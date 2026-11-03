import { Flame, History, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { BodyPartPicker } from '@/components/member/body-part-picker';
import { EquipmentPicker } from '@/components/member/equipment-picker';
import type {
    BodyPart,
    ExerciseSummaries,
    ExerciseSummary,
} from '@/components/member/exercise-data';
import { searchExercises } from '@/components/member/exercise-search';
import type { WorkoutSession } from '@/components/member/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';

/**
 * Convert one logged exercise id into a quick-pick chip entry.
 */
function chipFor(
    id: string,
    summaries: ExerciseSummaries,
): { slug: string; name: string; image_url: string } | null {
    const summary = summaries[id];
    if (summary === undefined) {
        return null;
    }
    return {
        slug: id,
        name: summary.name,
        image_url: summary.image_url ?? '',
    };
}

function QuickRow({
    title,
    icon: Icon,
    items,
    onPick,
}: {
    title: string;
    icon: typeof Flame;
    items: { slug: string; name: string; image_url: string }[];
    onPick: (slug: string) => void;
}) {
    if (items.length === 0) {
        return null;
    }
    return (
        <div>
            <p className="mb-2 flex items-center gap-1.5 text-xs font-bold tracking-wider text-[#8E8E93] uppercase">
                <Icon className="size-3.5" />
                {title}
            </p>
            <div className="-mx-1 flex scrollbar-none gap-2 overflow-x-auto px-1 pb-1">
                {items.map((item) => (
                    <button
                        key={item.slug}
                        type="button"
                        onClick={() => onPick(item.slug)}
                        className="flex w-20 shrink-0 flex-col items-center gap-1.5"
                    >
                        <span className="block size-16 overflow-hidden rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] transition-colors hover:border-[#AFFA01]/50">
                            {item.image_url !== '' && (
                                <img
                                    src={item.image_url}
                                    alt={item.name}
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                            )}
                        </span>
                        <span className="w-full truncate text-center text-[11px] font-medium text-[#F5F5F5]">
                            {item.name}
                        </span>
                    </button>
                ))}
            </div>
        </div>
    );
}

/**
 * Builder-style exercise picker: recent + popular quick picks, body-part
 * image tiles, equipment chips, and debounced catalog search. The caller
 * receives the chosen ExerciseSummary.
 */
export function ExerciseSearchDialog({
    open,
    onOpenChange,
    onPick,
    sessions = [],
    exerciseSummaries = {},
    bodyParts = [],
    equipment = [],
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPick: (exercise: ExerciseSummary) => void;
    sessions?: WorkoutSession[];
    exerciseSummaries?: ExerciseSummaries;
    bodyParts?: BodyPart[];
    equipment?: string[];
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [parts, setParts] = useState<string[]>([]);
    const [equip, setEquip] = useState<string[]>([]);
    const [results, setResults] = useState<ExerciseSummary[]>([]);
    const [loading, setLoading] = useState(false);

    // Recently-logged exercises, first-seen order (sessions are newest first).
    const recent = useMemo(() => {
        const seen: string[] = [];
        for (const session of sessions) {
            for (const set of session.sets) {
                if (!seen.includes(set.exercise_id)) {
                    seen.push(set.exercise_id);
                }
            }
        }
        return seen
            .slice(0, 6)
            .map((id) => chipFor(id, exerciseSummaries))
            .filter(
                (c): c is { slug: string; name: string; image_url: string } =>
                    c !== null,
            );
    }, [sessions, exerciseSummaries]);

    // Most-logged exercises by set count.
    const popular = useMemo(() => {
        const counts: Record<string, number> = {};
        for (const session of sessions) {
            for (const set of session.sets) {
                counts[set.exercise_id] = (counts[set.exercise_id] ?? 0) + 1;
            }
        }
        return Object.entries(counts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 6)
            .map(([id]) => chipFor(id, exerciseSummaries))
            .filter(
                (c): c is { slug: string; name: string; image_url: string } =>
                    c !== null,
            );
    }, [sessions, exerciseSummaries]);

    const filtered = parts.length > 0 || equip.length > 0;

    useEffect(() => {
        if (!open) {
            return;
        }
        setQuery('');
        setParts([]);
        setEquip([]);
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }
        let cancelled = false;
        setLoading(true);
        const timer = window.setTimeout(() => {
            searchExercises({
                q: query.trim() === '' ? query : undefined,
                body_part: parts.length > 0 ? parts : undefined,
                equipment: equip.length > 0 ? equip : undefined,
                limit: 30,
            })
                .then((data) => {
                    if (!cancelled) {
                        setResults(data.exercises);
                    }
                })
                .catch(() => {})
                .finally(() => {
                    if (!cancelled) {
                        setLoading(false);
                    }
                });
        }, 250);
        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [open, query, parts, equip]);

    function pickSlug(slug: string) {
        const hit = results.find((e) => e.slug === slug);
        if (hit) {
            onPick(hit);
            onOpenChange(false);
            return;
        }
        const summary = exerciseSummaries[slug];
        if (summary !== undefined) {
            onPick({
                slug,
                name: summary.name,
                detail: summary.detail,
                body_part: '',
                equipment: summary.equipment ?? '',
                target: '',
                level: '',
                image_url: summary.image_url ?? '',
            });
            onOpenChange(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[85vh] flex-col overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {t('member.progress.pick_exercise')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('member.progress.pick_exercise_hint')}
                    </DialogDescription>
                </DialogHeader>

                <div className="relative">
                    <Search className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#8E8E93]" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('member.exercise.search_placeholder')}
                        className="pl-10"
                        autoFocus
                    />
                </div>

                {!filtered && query.trim() === '' && (
                    <div className="space-y-4">
                        <QuickRow
                            title={t('member.progress.recent')}
                            icon={History}
                            items={recent}
                            onPick={pickSlug}
                        />
                        <QuickRow
                            title={t('member.progress.popular')}
                            icon={Flame}
                            items={popular}
                            onPick={pickSlug}
                        />
                    </div>
                )}

                {bodyParts.length > 0 && (
                    <BodyPartPicker
                        bodyParts={bodyParts}
                        value={parts}
                        onChange={setParts}
                    />
                )}
                {equipment.length > 0 && (
                    <EquipmentPicker
                        equipment={equipment}
                        value={equip}
                        onChange={setEquip}
                    />
                )}

                <div className="divide-y divide-[#2E2E2E] rounded-xl border border-[#2E2E2E]">
                    {loading && results.length === 0 ? (
                        <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                            {t('common.loading')}
                        </p>
                    ) : results.length === 0 ? (
                        <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                            {t('member.exercise.no_results')}
                        </p>
                    ) : (
                        results.map((ex) => (
                            <button
                                key={ex.slug}
                                type="button"
                                onClick={() => {
                                    onPick(ex);
                                    onOpenChange(false);
                                }}
                                className="flex w-full items-center gap-3 px-3.5 py-2.5 text-left transition-colors hover:bg-[#262626]"
                            >
                                <img
                                    src={ex.image_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-10 shrink-0 rounded-lg object-cover"
                                />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                                        {ex.name}
                                    </p>
                                    <p className="truncate text-xs text-[#8E8E93]">
                                        {ex.detail}
                                    </p>
                                </div>
                            </button>
                        ))
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
