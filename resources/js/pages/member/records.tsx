import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Search, Trophy } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    exerciseName,
    tracksWeight,
    type BodyPart,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import { MobileShell } from '@/components/member/mobile-shell';
import type { WorkoutSession } from '@/components/member/types';
import { prByExercise } from '@/components/member/workout-progress';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { show as exerciseShow } from '@/routes/member/exercises';

/**
 * Filter-tile ids that expand to multiple dataset body parts — mirrors
 * ExerciseCatalog::GROUPED_PARTS so the "Arms" chip matches both arm parts.
 */
const GROUPED_PARTS: Record<string, string[]> = {
    arms: ['upper arms', 'lower arms'],
};

function matchesPart(chipId: string, bodyPart: string | undefined): boolean {
    const group = GROUPED_PARTS[chipId];
    if (group !== undefined) {
        return bodyPart !== undefined && group.includes(bodyPart);
    }
    return bodyPart === chipId;
}

function formatDate(date: string): string {
    return new Date(`${date.slice(0, 10)}T12:00:00`).toLocaleDateString(
        undefined,
        { month: 'short', day: 'numeric', year: 'numeric' },
    );
}

export default function MemberRecords({
    sessions,
    exerciseSummaries,
    bodyParts,
}: {
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
    bodyParts: BodyPart[];
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [part, setPart] = useState('');

    const entries = useMemo(
        () =>
            Object.entries(prByExercise(sessions)).sort(
                (a, b) =>
                    b[1].weight_kg - a[1].weight_kg || b[1].reps - a[1].reps,
            ),
        [sessions],
    );

    const usedParts = useMemo(() => {
        const present = new Set(
            entries
                .map(([id]) => exerciseSummaries[id]?.body_part)
                .filter((p): p is string => p !== undefined),
        );
        return bodyParts.filter(
            (tile) =>
                tile.id !== '' &&
                (GROUPED_PARTS[tile.id] ?? [tile.id]).some((p) =>
                    present.has(p),
                ),
        );
    }, [entries, exerciseSummaries, bodyParts]);

    const q = query.trim().toLowerCase();
    const filtered = entries.filter(([id]) => {
        if (
            part !== '' &&
            !matchesPart(part, exerciseSummaries[id]?.body_part)
        ) {
            return false;
        }
        return (
            q === '' ||
            exerciseName(exerciseSummaries, id).toLowerCase().includes(q)
        );
    });

    function partLabel(partId: string, fallback: string): string {
        const key = `exercise_values.body_part.${partId}`;
        const text = t(key);
        return text === key ? fallback : text;
    }

    return (
        <MobileShell>
            <Head title={t('member.records.title')} />

            <div className="flex items-center gap-3 pt-2">
                <button
                    type="button"
                    onClick={() => window.history.back()}
                    aria-label="Back"
                    className="-ml-1 flex size-9 shrink-0 items-center justify-center rounded-full border border-[#2E2E2E] bg-[#1F1F1F] text-[#8E8E93] transition-colors hover:border-[#AFFA01]/40 hover:text-[#F5F5F5]"
                >
                    <ArrowLeft className="size-4" />
                </button>
                <h1 className="text-xl font-bold tracking-tight text-[#F5F5F5]">
                    {t('member.records.title')}
                </h1>
                {entries.length > 0 && (
                    <span className="ml-auto rounded-full border border-[#AFFA01]/30 bg-[#AFFA01]/10 px-2.5 py-0.5 text-xs font-bold text-[#AFFA01]">
                        {entries.length}
                    </span>
                )}
            </div>

            {entries.length === 0 ? (
                <div className="mt-8 flex flex-col items-center gap-2 rounded-2xl border border-[#2E2E2E] bg-[#262626] p-8 text-center">
                    <Trophy className="size-7 text-[#AFFA01]" />
                    <p className="text-sm text-muted-foreground">
                        {t('member.records.empty')}
                    </p>
                </div>
            ) : (
                <>
                    <div className="relative mt-4">
                        <Search className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#8E8E93]" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t('member.records.search')}
                            className="pl-10"
                        />
                    </div>

                    <div className="-mx-1 mt-3 flex scrollbar-none gap-2 overflow-x-auto px-1 pb-1">
                        <button
                            type="button"
                            onClick={() => setPart('')}
                            className={cn(
                                'shrink-0 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors',
                                part === ''
                                    ? 'border-[#AFFA01]/60 bg-[#AFFA01]/10 text-[#AFFA01]'
                                    : 'border-[#2E2E2E] text-[#8E8E93] hover:text-[#F5F5F5]',
                            )}
                        >
                            {t('member.records.all')}
                        </button>
                        {usedParts.map((tile) => (
                            <button
                                key={tile.id}
                                type="button"
                                onClick={() =>
                                    setPart(part === tile.id ? '' : tile.id)
                                }
                                className={cn(
                                    'shrink-0 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors',
                                    part === tile.id
                                        ? 'border-[#AFFA01]/60 bg-[#AFFA01]/10 text-[#AFFA01]'
                                        : 'border-[#2E2E2E] text-[#8E8E93] hover:text-[#F5F5F5]',
                                )}
                            >
                                {partLabel(tile.id, tile.label)}
                            </button>
                        ))}
                    </div>

                    <div className="mt-3 divide-y divide-[#2E2E2E] rounded-2xl border border-[#2E2E2E] bg-[#1A1A1A] px-4">
                        {filtered.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                {t('member.records.no_matches')}
                            </p>
                        ) : (
                            filtered.map(([id, pr]) => {
                                const weighted = tracksWeight(
                                    exerciseSummaries[id]?.equipment,
                                );
                                const e1rm =
                                    pr.weight_kg > 0
                                        ? Math.round(
                                              pr.weight_kg *
                                                  (1 + pr.reps / 30) *
                                                  10,
                                          ) / 10
                                        : null;
                                return (
                                    <Link
                                        key={id}
                                        href={exerciseShow(id).url}
                                        className="flex items-center gap-3 py-3 transition-colors hover:text-[#AFFA01]"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                                                {exerciseName(
                                                    exerciseSummaries,
                                                    id,
                                                )}
                                            </p>
                                            <p className="mt-0.5 text-xs text-[#8E8E93]">
                                                {t('member.records.achieved')}{' '}
                                                {formatDate(pr.date)}
                                                {e1rm !== null &&
                                                    weighted &&
                                                    ` · e1RM ~${e1rm}kg`}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-sm font-semibold text-[#AFFA01]">
                                            {pr.weight_kg > 0
                                                ? `${pr.weight_kg}kg × ${pr.reps}`
                                                : `${pr.reps} reps`}
                                        </span>
                                        <ChevronRight className="size-4 shrink-0 text-[#3A3A3A]" />
                                    </Link>
                                );
                            })
                        )}
                    </div>
                </>
            )}
        </MobileShell>
    );
}
