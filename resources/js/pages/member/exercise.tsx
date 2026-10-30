import { Head, Link, usePage } from '@inertiajs/react';
import {
    Infinity as InfinityIcon,
    NotebookPen,
    Search,
    SignalHigh,
    SignalLow,
    SignalMedium,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AddToPlaylist } from '@/components/member/add-to-playlist';
import { BmiCard } from '@/components/member/bmi-card';
import { BodyFatCard } from '@/components/member/body-fat-card';
import {
    BodyPartPicker,
    useBodyPartLabel,
} from '@/components/member/body-part-picker';
import { EquipmentPicker } from '@/components/member/equipment-picker';
import {
    type ExerciseSummaries,
    type ExerciseSummary,
} from '@/components/member/exercise-data';
import { searchExercises } from '@/components/member/exercise-search';
import { MobileShell } from '@/components/member/mobile-shell';
import { PlaylistManager } from '@/components/member/playlist-manager';
import { QuickLogSet } from '@/components/member/quick-log-set';
import { SessionSummary } from '@/components/member/session-summary';
import { TodaysLog } from '@/components/member/todays-log';
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
import { useTranslation } from '@/lib/i18n';
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
    const { t, locale } = useTranslation();
    const partLabel = useBodyPartLabel();
    const [tab, setTab] = useState<Tab>(
        url.includes('tab=track') ? 'track' : 'explore',
    );
    const [bodyPart, setBodyPart] = useState<string[]>([]);
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
            const param = new URLSearchParams(
                url.split('?')[1] ?? '',
            ).get('session');
            return param === null ? null : Number(param);
        },
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [logTarget, setLogTarget] = useState<ExerciseSummary | null>(null);
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

    /**
     * Translated exercise data value (equipment/body part/level/target),
     * falling back to the raw catalog value when no line exists.
     */
    function tv(
        kind: 'equipment' | 'body_part' | 'level' | 'target',
        value: string,
    ): string {
        const key = `exercise_values.${kind}.${value}`;
        const text = t(key);
        return text === key ? value : text;
    }

    /**
     * "Barbell · Pectorals" — recomposed from translated parts in Amharic,
     * server-provided English detail otherwise.
     */
    function exerciseDetailText(ex: ExerciseSummary): string {
        return locale === 'am'
            ? `${tv('equipment', ex.equipment)} · ${tv('target', ex.target)}`
            : ex.detail;
    }

    const bodyPartLabel =
        bodyPart.length === 0
            ? 'matching'
            : bodyParts
                  .filter((p) => bodyPart.includes(p.id))
                  .map((p) => partLabel(p.id))
                  .join(' + ');

    function exerciseCard(ex: ExerciseSummary) {
        const LevelIcon = LEVEL_ICONS[ex.level] ?? SignalLow;
        return (
            <div
                key={ex.slug}
                className="flex flex-col overflow-hidden rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] transition-all hover:border-[#AFFA01]/40"
            >
                <Link
                    href={exerciseShow(ex.slug).url}
                    prefetch
                    tabIndex={-1}
                    className="block"
                >
                    <img
                        src={ex.image_url}
                        alt=""
                        loading="lazy"
                        className="aspect-square w-full object-cover"
                    />
                </Link>
                <div className="flex flex-1 flex-col p-3">
                    <Link
                        href={exerciseShow(ex.slug).url}
                        prefetch
                        className="min-w-0"
                    >
                        <p className="line-clamp-2 text-sm font-bold text-[#F5F5F5] transition-colors hover:text-[#AFFA01]">
                            {ex.name}
                        </p>
                        <p className="mt-0.5 line-clamp-2 text-xs text-[#8E8E93]">
                            {exerciseDetailText(ex)}
                        </p>
                    </Link>
                    <span className="mt-2 self-start rounded-lg bg-[#AFFA01] px-4 py-1 text-xs font-semibold text-[#0B1F0B] capitalize">
                        {tv('body_part', ex.body_part)}
                    </span>
                    <div className="mt-auto flex items-center justify-between gap-2 pt-2">
                        <div className="flex items-center gap-2.5 text-[11px] text-[#8E8E93]">
                            {ex.level !== '' && (
                                <span className="flex items-center gap-1 capitalize">
                                    <LevelIcon className="size-3.5" />
                                    {tv('level', ex.level)}
                                </span>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setLogTarget(ex)}
                                aria-label={`Log a set of ${ex.name}`}
                                className="flex size-8 shrink-0 items-center justify-center rounded-xl border border-[#2E2E2E] text-[#8E8E93] transition-all hover:border-[#AFFA01]/40 hover:text-[#AFFA01]"
                            >
                                <NotebookPen className="size-4" />
                            </button>
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
                                            equipment: ex.equipment,
                                        },
                                    }));
                                    updatePlaylist(updated);
                                }}
                                onCreateNew={() => setCreateOpen(true)}
                            />
                        </div>
                    </div>
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
            <Head title={t('member.exercise.title')} />
            <div className="grid grid-cols-2 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] p-1.5">
                {(['explore', 'track'] as const).map((tabName) => (
                    <button
                        key={tabName}
                        type="button"
                        onClick={() => setTab(tabName)}
                        className={cn(
                            'rounded-xl py-2 text-xs font-bold tracking-wider uppercase transition-all',
                            tab === tabName
                                ? 'bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] text-black shadow-md'
                                : 'text-[#8E8E93] hover:text-[#F5F5F5]',
                        )}
                    >
                        {t(`member.exercise.${tabName}`)}
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
                        <Search className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#8E8E93]" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t(
                                'member.exercise.search_placeholder',
                            )}
                            className="pl-10"
                        />
                    </div>

                    <div>
                        <h2 className="mb-2.5 text-xs font-bold tracking-wider text-[#AFFA01] uppercase">
                            {t('member.exercise.choose_body_part')}
                        </h2>
                        <BodyPartPicker
                            bodyParts={bodyParts}
                            value={bodyPart}
                            onChange={setBodyPart}
                        />
                    </div>

                    <div>
                        <h2 className="mb-2 text-sm font-medium">
                            {t('member.exercise.choose_equipment')}
                        </h2>
                        <EquipmentPicker
                            equipment={equipment}
                            value={equipmentSel}
                            onChange={setEquipmentSel}
                        />
                    </div>

                    <div>
                        <h2 className="mb-2 text-sm font-medium">
                            {t('member.exercise.choose_level')}
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
                                                ? 'border-lime-500 bg-lime-300/40 text-[#F5F5F5]'
                                                : 'border-input text-muted-foreground',
                                        )}
                                    >
                                        <Icon className="size-5 shrink-0" />
                                        <span className="text-[10px] leading-none capitalize">
                                            {level === ''
                                                ? t('member.exercise.level_all')
                                                : tv('level', level)}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {results.length === 0 && !loading && (
                        <Card>
                            <CardContent className="p-0">
                                <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                    {t('member.exercise.no_results')}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {results.length === 0 &&
                        !loading &&
                        fallback.length > 0 && (
                            <div>
                                <p className="mb-2 text-xs font-medium text-muted-foreground">
                                    {t('member.exercise.fallback_title', {
                                        part: bodyPartLabel,
                                    })}
                                </p>
                                <div className="grid grid-cols-2 gap-3">
                                    {fallback.map(exerciseCard)}
                                </div>
                            </div>
                        )}

                    <div className="grid grid-cols-2 gap-3">
                        {results.map(exerciseCard)}
                    </div>

                    {results.length < total && (
                        <Button
                            variant="outline"
                            className="w-full"
                            disabled={loading}
                            onClick={loadMore}
                        >
                            {loading
                                ? t('common.loading')
                                : `${t('member.exercise.load_more')} (${total - results.length})`}
                        </Button>
                    )}
                </div>
            ) : (
                <div className="mt-6 space-y-4">
                    <SessionSummary
                        sessions={sessions}
                        exerciseSummaries={exerciseSummaries}
                    />

                    {activeSession === null && (
                        <TodaysLog
                            sessions={todaysSessions}
                            playlists={playlists}
                            exerciseSummaries={exerciseSummaries}
                            onOpen={(s) => setActiveSessionId(s.id)}
                        />
                    )}

                    {activeSession !== null && (
                        <WorkoutSessionCard
                            session={activeSession}
                            playlist={activePlaylist}
                            sessions={sessions}
                            exerciseSummaries={exerciseSummaries}
                            onSessionChange={upsertSession}
                            onBack={() => setActiveSessionId(null)}
                        />
                    )}

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

                    <BmiCard />

                    <BodyFatCard />
                </div>
            )}

            <QuickLogSet
                slug={logTarget?.slug ?? ''}
                name={logTarget?.name ?? ''}
                equipment={logTarget?.equipment ?? ''}
                sessions={sessions}
                open={logTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setLogTarget(null);
                    }
                }}
                onLogged={(session) => {
                    setSessions((list) =>
                        list.some((s) => s.id === session.id)
                            ? list.map((s) =>
                                  s.id === session.id ? session : s,
                              )
                            : [session, ...list],
                    );
                    if (logTarget !== null) {
                        setExerciseSummaries((map) => ({
                            ...map,
                            [logTarget.slug]: {
                                name: logTarget.name,
                                detail: logTarget.detail,
                                equipment: logTarget.equipment,
                                image_url: logTarget.image_url,
                            },
                        }));
                    }
                }}
            />
        </MobileShell>
    );
}
