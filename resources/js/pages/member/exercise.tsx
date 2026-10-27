import { Head, Link, usePage } from '@inertiajs/react';
import {
    Infinity as InfinityIcon,
    Search,
    SignalHigh,
    SignalLow,
    SignalMedium,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AddToPlaylist } from '@/components/member/add-to-playlist';
import { EquipmentPicker } from '@/components/member/equipment-picker';
import {
    bodyPartIcon,
    type ExerciseSummaries,
    type ExerciseSummary,
} from '@/components/member/exercise-data';
import { searchExercises } from '@/components/member/exercise-search';
import { MobileShell } from '@/components/member/mobile-shell';
import { PlaylistManager } from '@/components/member/playlist-manager';
import { SessionStart } from '@/components/member/session-start';
import { SessionSummary } from '@/components/member/session-summary';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import {
    WeightCard,
    type WeightLogEntry,
} from '@/components/member/weight-card';
import { WorkoutSessionCard } from '@/components/member/workout-session';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { show as exerciseShow } from '@/routes/member/exercises';

type Tab = 'explore' | 'track';

const LEVEL_ICONS: Record<string, LucideIcon> = {
    '': InfinityIcon,
    beginner: SignalLow,
    intermediate: SignalMedium,
    advanced: SignalHigh,
};

export default function MemberExercise({
    playlists: initialPlaylists,
    sessions: initialSessions,
    exerciseSummaries: initialSummaries,
    bodyParts,
    equipment,
    levels,
    initialExercises,
    initialTotal,
    weightLogs: initialWeightLogs,
}: {
    playlists: ExercisePlaylist[];
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
    bodyParts: { id: string; label: string; count: number }[];
    equipment: string[];
    levels: string[];
    initialExercises: ExerciseSummary[];
    initialTotal: number;
    weightLogs: WeightLogEntry[];
}) {
    const { url } = usePage();
    const [tab, setTab] = useState<Tab>(
        url.includes('tab=track') ? 'track' : 'explore',
    );
    const [bodyPart, setBodyPart] = useState<string[]>(['chest']);
    const [equipmentSel, setEquipmentSel] = useState<string[]>([]);
    const [levelSel, setLevelSel] = useState<string>('');
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ExerciseSummary[]>(initialExercises);
    const [total, setTotal] = useState(initialTotal);
    const [loading, setLoading] = useState(false);
    const [playlists, setPlaylists] =
        useState<ExercisePlaylist[]>(initialPlaylists);
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [exerciseSummaries, setExerciseSummaries] =
        useState<ExerciseSummaries>(initialSummaries);
    const [activeSessionId, setActiveSessionId] = useState<number | null>(
        () => {
            const param = new URLSearchParams(window.location.search).get(
                'session',
            );
            return param === null ? null : Number(param);
        },
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [weightLogs, setWeightLogs] =
        useState<WeightLogEntry[]>(initialWeightLogs);
    const [fallback, setFallback] = useState<ExerciseSummary[]>([]);
    const skipFirstSearch = useRef(true);

    const todayKey = new Date().toISOString().slice(0, 10);
    const todaysSessions = sessions.filter((s) => s.date === todayKey);
    const activeSession =
        sessions.find((s) => s.id === activeSessionId) ?? null;
    const activePlaylist =
        playlists.find((p) => p.id === activeSession?.exercise_playlist_id) ??
        null;

    useEffect(() => {
        if (skipFirstSearch.current) {
            skipFirstSearch.current = false;
            return;
        }

        let cancelled = false;
        setLoading(true);

        const timer = window.setTimeout(() => {
            searchExercises({
                q: query,
                body_part: bodyPart,
                equipment: equipmentSel,
                level: levelSel,
            })
                .then((data) => {
                    if (cancelled) {
                        return;
                    }
                    setResults(data.exercises);
                    setTotal(data.total);
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
    }, [query, bodyPart, equipmentSel, levelSel]);

    // When the filters match nothing, offer exercises for the target muscle
    // outside the equipment filter.
    useEffect(() => {
        if (loading || results.length > 0) {
            setFallback([]);
            return;
        }

        let cancelled = false;
        searchExercises({
            q: query,
            body_part: bodyPart,
            level: levelSel,
            limit: 10,
        })
            .then((data) => {
                if (!cancelled) {
                    setFallback(data.exercises);
                }
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
    }, [results, loading, query, bodyPart, levelSel]);

    const bodyPartLabel =
        bodyPart.length === 0
            ? 'matching'
            : bodyParts
                  .filter((p) => bodyPart.includes(p.id))
                  .map((p) => p.label)
                  .join(' + ');

    function exerciseRow(ex: ExerciseSummary) {
        return (
            <div
                key={ex.slug}
                className="px-4 py-3 transition-colors hover:bg-accent/50"
            >
                <div className="flex items-center gap-3">
                    <Link
                        href={exerciseShow(ex.slug).url}
                        prefetch
                        tabIndex={-1}
                        className="shrink-0"
                    >
                        <img
                            src={ex.image_url}
                            alt=""
                            loading="lazy"
                            className="size-10 rounded-lg object-cover"
                        />
                    </Link>
                    <Link
                        href={exerciseShow(ex.slug).url}
                        prefetch
                        className="min-w-0 flex-1"
                    >
                        <p className="text-sm font-medium">{ex.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {ex.detail}
                        </p>
                    </Link>
                    <AddToPlaylist
                        playlists={playlists}
                        exerciseId={ex.slug}
                        exerciseName={ex.name}
                        onChange={(updated) => {
                            setExerciseSummaries((map) => ({
                                ...map,
                                [ex.slug]: {
                                    name: ex.name,
                                    detail: ex.detail,
                                },
                            }));
                            updatePlaylist(updated);
                        }}
                        onCreateNew={() => setCreateOpen(true)}
                    />
                </div>
            </div>
        );
    }

    function updatePlaylist(updated: ExercisePlaylist) {
        setPlaylists((list) =>
            list.some((p) => p.id === updated.id)
                ? list.map((p) => (p.id === updated.id ? updated : p))
                : [...list, updated],
        );
    }

    function upsertSession(next: WorkoutSession) {
        setSessions((list) =>
            list.some((s) => s.id === next.id)
                ? list.map((s) => (s.id === next.id ? next : s))
                : [next, ...list],
        );
        setActiveSessionId(next.id);
    }

    function loadMore() {
        setLoading(true);
        searchExercises({
            q: query,
            body_part: bodyPart,
            equipment: equipmentSel,
            level: levelSel,
            offset: results.length,
        })
            .then((data) => {
                setResults((list) => [...list, ...data.exercises]);
                setTotal(data.total);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }

    return (
        <MobileShell>
            <Head title="Exercise" />
            <div className="grid grid-cols-2 rounded-full bg-muted p-1">
                {(['explore', 'track'] as const).map((t) => (
                    <button
                        key={t}
                        type="button"
                        onClick={() => setTab(t)}
                        className={cn(
                            'rounded-full py-1.5 text-sm font-medium capitalize',
                            tab === t
                                ? 'bg-lime-400 text-lime-950'
                                : 'text-muted-foreground',
                        )}
                    >
                        {t}
                    </button>
                ))}
            </div>

            {tab === 'explore' ? (
                <div className="mt-6 space-y-4">
                    <PlaylistManager
                        playlists={playlists}
                        onChange={setPlaylists}
                        createOpen={createOpen}
                        onCreateOpenChange={setCreateOpen}
                        onStartSession={upsertSession}
                    />

                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search 1,324 exercises"
                            className="pl-9"
                        />
                    </div>

                    <div>
                        <h2 className="mb-2 text-sm font-medium">
                            Choose target body part
                        </h2>
                        <div className="grid grid-cols-5 gap-2">
                            {bodyParts.map(({ id, label }) => {
                                const Icon = bodyPartIcon(id);
                                const active =
                                    id === ''
                                        ? bodyPart.length === 0
                                        : bodyPart.includes(id);
                                return (
                                    <button
                                        key={id}
                                        type="button"
                                        onClick={() =>
                                            setBodyPart((list) =>
                                                id === ''
                                                    ? []
                                                    : list.includes(id)
                                                      ? list.filter(
                                                            (p) => p !== id,
                                                        )
                                                      : [...list, id],
                                            )
                                        }
                                        title={label}
                                        className={cn(
                                            'flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border px-1',
                                            active
                                                ? 'border-lime-500 bg-lime-300/40 text-lime-900 dark:text-lime-100'
                                                : 'border-input text-muted-foreground',
                                        )}
                                    >
                                        <Icon className="size-5 shrink-0" />
                                        <span className="text-[10px] leading-none">
                                            {label}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        {bodyPart.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {bodyPart.map((id) => {
                                    const chipLabel =
                                        bodyParts.find((p) => p.id === id)
                                            ?.label ?? id;
                                    return (
                                        <button
                                            key={id}
                                            type="button"
                                            onClick={() =>
                                                setBodyPart((list) =>
                                                    list.filter(
                                                        (p) => p !== id,
                                                    ),
                                                )
                                            }
                                            aria-label={`Remove ${chipLabel} filter`}
                                            className="flex items-center gap-1 rounded-full border border-lime-500 bg-lime-300/40 px-2.5 py-1 text-xs text-lime-900 capitalize dark:text-lime-100"
                                        >
                                            {chipLabel}
                                            <X className="size-3" />
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <div>
                        <h2 className="mb-2 text-sm font-medium">
                            Choose equipment
                        </h2>
                        <EquipmentPicker
                            equipment={equipment}
                            value={equipmentSel}
                            onChange={setEquipmentSel}
                        />
                    </div>

                    <div>
                        <h2 className="mb-2 text-sm font-medium">
                            Choose level
                        </h2>
                        <div className="grid grid-cols-4 gap-2">
                            {['', ...levels].map((level) => {
                                const Icon = LEVEL_ICONS[level] ?? SignalLow;
                                return (
                                    <button
                                        key={level}
                                        type="button"
                                        onClick={() =>
                                            setLevelSel(
                                                levelSel === level ? '' : level,
                                            )
                                        }
                                        className={cn(
                                            'flex h-14 flex-col items-center justify-center gap-1 rounded-xl border px-1',
                                            levelSel === level
                                                ? 'border-lime-500 bg-lime-300/40 text-lime-900 dark:text-lime-100'
                                                : 'border-input text-muted-foreground',
                                        )}
                                    >
                                        <Icon className="size-5 shrink-0" />
                                        <span className="text-[10px] leading-none capitalize">
                                            {level === '' ? 'All' : level}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <Card>
                        <CardContent className="divide-y p-0">
                            {results.length === 0 && !loading && (
                                <>
                                    <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                        No exercises match — widen the filters.
                                    </p>
                                    {fallback.length > 0 && (
                                        <>
                                            <p className="px-4 py-2 text-xs font-medium text-muted-foreground">
                                                Try these {bodyPartLabel}{' '}
                                                exercises instead
                                            </p>
                                            {fallback.map(exerciseRow)}
                                        </>
                                    )}
                                </>
                            )}
                            {results.map(exerciseRow)}
                        </CardContent>
                    </Card>

                    {results.length < total && (
                        <Button
                            variant="outline"
                            className="w-full"
                            disabled={loading}
                            onClick={loadMore}
                        >
                            {loading
                                ? 'Loading…'
                                : `Load more (${total - results.length} left)`}
                        </Button>
                    )}
                </div>
            ) : (
                <div className="mt-6 space-y-4">
                    <WeightCard
                        logs={weightLogs}
                        onLogged={(log) =>
                            setWeightLogs((list) => [
                                log,
                                ...list.filter(
                                    (l) => l.logged_on !== log.logged_on,
                                ),
                            ])
                        }
                    />

                    <SessionSummary
                        sessions={sessions}
                        exerciseSummaries={exerciseSummaries}
                    />

                    {activeSession === null ? (
                        <SessionStart
                            playlists={playlists}
                            todaysSessions={todaysSessions}
                            onStart={upsertSession}
                        />
                    ) : (
                        <WorkoutSessionCard
                            session={activeSession}
                            playlist={activePlaylist}
                            sessions={sessions}
                            exerciseSummaries={exerciseSummaries}
                            onSessionChange={upsertSession}
                            onBack={() => setActiveSessionId(null)}
                        />
                    )}
                </div>
            )}
        </MobileShell>
    );
}
