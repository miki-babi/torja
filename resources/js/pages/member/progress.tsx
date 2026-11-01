import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, Flame, NotebookPen, TrendingUp } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { BmiCard } from '@/components/member/bmi-card';
import { BodyFatCard } from '@/components/member/body-fat-card';
import {
    exerciseName,
    tracksWeight,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import { MobileShell } from '@/components/member/mobile-shell';
import { QuickLogSet } from '@/components/member/quick-log-set';
import { TodaysLog } from '@/components/member/todays-log';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import {
    WeightCard,
    type WeightLogEntry,
} from '@/components/member/weight-card';
import {
    exerciseHistory,
    exerciseSeries,
    prByExercise,
    sessionExerciseCount,
    sessionsThisWeek,
    sessionVolume,
    setsForExercise,
    setSummary,
    totalVolume,
    weeklyVolumeSeries,
} from '@/components/member/workout-progress';
import { WorkoutSessionCard } from '@/components/member/workout-session';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { exercise, today } from '@/routes/member';

type ActivityDay = { date: string; label: string; active: boolean };

function ChartTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: { value?: number; name?: string }[];
    label?: string;
}) {
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="rounded-md border bg-popover px-3 py-2 text-xs shadow-md">
            <p className="mb-1 font-medium">{label}</p>
            {payload.map((p, i) => (
                <p key={i} className="text-muted-foreground">
                    {p.name}:{' '}
                    <span className="font-medium text-foreground">
                        {Number(p.value).toLocaleString()}
                    </span>
                </p>
            ))}
        </div>
    );
}

function formatDay(date: string): string {
    return new Date(`${date.slice(0, 10)}T12:00:00`).toLocaleDateString(
        undefined,
        {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
        },
    );
}

export default function MemberProgress({
    playlists,
    sessions: initialSessions,
    exerciseSummaries,
    weightLogs: initialWeightLogs,
    streak,
    activityWeek,
}: {
    playlists: ExercisePlaylist[];
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
    weightLogs: WeightLogEntry[];
    streak: number;
    activityWeek: ActivityDay[];
}) {
    const { url } = usePage();
    const { t } = useTranslation();
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [weightLogs, setWeightLogs] =
        useState<WeightLogEntry[]>(initialWeightLogs);
    const [activeSessionId, setActiveSessionId] = useState<number | null>(
        () => {
            const param = new URLSearchParams(url.split('?')[1] ?? '').get(
                'session',
            );
            return param === null ? null : Number(param);
        },
    );
    const [selectedExercise, setSelectedExercise] = useState<string | null>(
        null,
    );
    const [logTarget, setLogTarget] = useState<string | null>(null);

    const todayKey = new Date().toISOString().slice(0, 10);
    const todaysSessions = sessions.filter((s) => s.date === todayKey);
    const activeSession =
        sessions.find((s) => s.id === activeSessionId) ?? null;
    const activePlaylist =
        playlists.find((p) => p.id === activeSession?.exercise_playlist_id) ??
        null;

    const loggedSessions = sessions.filter((s) => s.sets.length > 0);
    const prs = useMemo(() => prByExercise(sessions), [sessions]);
    const prEntries = Object.entries(prs).sort(
        (a, b) => b[1].weight_kg - a[1].weight_kg || b[1].reps - a[1].reps,
    );

    // Logged exercises sorted by most recent use — feeds the picker chips.
    const loggedExercises = useMemo(() => {
        const lastDate: Record<string, string> = {};
        for (const session of sessions) {
            for (const set of session.sets) {
                if (
                    lastDate[set.exercise_id] === undefined ||
                    session.date > lastDate[set.exercise_id]
                ) {
                    lastDate[set.exercise_id] = session.date;
                }
            }
        }
        return Object.entries(lastDate)
            .sort((a, b) => b[1].localeCompare(a[1]))
            .map(([id]) => id);
    }, [sessions]);

    const selected =
        selectedExercise !== null && loggedExercises.includes(selectedExercise)
            ? selectedExercise
            : (loggedExercises[0] ?? null);

    const selectedWeighted = tracksWeight(
        selected === null ? null : exerciseSummaries[selected]?.equipment,
    );
    const selectedSeries =
        selected === null
            ? []
            : exerciseSeries(sessions, selected).map((p) => ({
                  label: formatDay(p.date),
                  kg: p.weight_kg,
                  reps: p.reps,
              }));
    const selectedPr = selected === null ? null : prs[selected];
    const selectedHistory =
        selected === null
            ? []
            : exerciseHistory(sessions, selected, undefined, 3);

    const volumeSeries = weeklyVolumeSeries(sessions, 10);

    function upsertSession(next: WorkoutSession) {
        setSessions((list) =>
            list.some((s) => s.id === next.id)
                ? list.map((s) => (s.id === next.id ? next : s))
                : [next, ...list],
        );
        setActiveSessionId(next.id);
    }

    return (
        <MobileShell>
            <Head title={t('member.progress.title')} />

            <div className="flex items-center justify-between pt-2">
                <h1 className="text-xl font-bold tracking-tight text-[#F5F5F5]">
                    {t('member.progress.title')}
                </h1>
                <div className="flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]">
                    <Flame className="size-3.5 fill-[#AFFA01] text-[#AFFA01]" />
                    <span>
                        {t('member.home.day_streak', {
                            count: streak,
                        })}
                    </span>
                </div>
            </div>

            {loggedSessions.length === 0 ? (
                <Card className="mt-5">
                    <CardContent className="flex flex-col items-center gap-2 p-6 text-center">
                        <TrendingUp className="size-6 text-[#AFFA01]" />
                        <p className="text-sm font-medium">
                            {t('member.progress.empty_title')}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('member.progress.empty_hint')}
                        </p>
                        <div className="mt-2 flex gap-2">
                            <Button asChild>
                                <Link href={today().url}>
                                    {t('member.progress.start_workout')}
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={exercise().url}>
                                    {t('member.progress.browse_exercises')}
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ) : (
                <div className="mt-5 space-y-4">
                    {/* Stats */}
                    <div className="grid grid-cols-2 gap-2.5 text-center">
                        {[
                            {
                                value: String(sessionsThisWeek(sessions)),
                                label: t('member.progress.stat_week'),
                                accent: true,
                            },
                            {
                                value: String(loggedSessions.length),
                                label: t('member.progress.stat_total'),
                            },
                            {
                                value: Math.round(
                                    totalVolume(sessions),
                                ).toLocaleString(),
                                label: t('member.progress.stat_volume'),
                            },
                            {
                                value: String(streak),
                                label: t('member.progress.stat_streak'),
                            },
                        ].map((stat) => (
                            <div
                                key={stat.label}
                                className="rounded-2xl border border-[#2E2E2E] bg-[#262626] py-3.5"
                            >
                                <p
                                    className={cn(
                                        'text-2xl font-black',
                                        stat.accent
                                            ? 'text-[#AFFA01]'
                                            : 'text-[#F5F5F5]',
                                    )}
                                >
                                    {stat.value}
                                </p>
                                <p className="mt-0.5 text-xs font-medium text-[#A1A1AA]">
                                    {stat.label}
                                </p>
                            </div>
                        ))}
                    </div>

                    {/* Today's log / active session */}
                    {activeSession === null ? (
                        <TodaysLog
                            sessions={todaysSessions}
                            playlists={playlists}
                            exerciseSummaries={exerciseSummaries}
                            onOpen={(s) => setActiveSessionId(s.id)}
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

                    {/* Weekly activity strip */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('member.progress.this_week')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex justify-between">
                                {activityWeek.map((day) => (
                                    <div
                                        key={day.date}
                                        className="flex flex-col items-center gap-1.5"
                                    >
                                        <span className="text-[10px] font-medium text-[#8E8E93]">
                                            {day.label}
                                        </span>
                                        <span
                                            className={cn(
                                                'size-2.5 rounded-full',
                                                day.active
                                                    ? 'bg-[#AFFA01]'
                                                    : 'bg-[#2E2E2E]',
                                            )}
                                        />
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Volume trend */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('member.progress.volume_trend')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-36 w-full">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart
                                        data={volumeSeries}
                                        margin={{
                                            top: 4,
                                            right: 4,
                                            bottom: 0,
                                            left: -18,
                                        }}
                                    >
                                        <CartesianGrid
                                            vertical={false}
                                            stroke="var(--border)"
                                            strokeDasharray="4 4"
                                        />
                                        <XAxis
                                            dataKey="label"
                                            tickLine={false}
                                            axisLine={false}
                                            tick={{
                                                fontSize: 10,
                                                fill: 'var(--muted-foreground)',
                                            }}
                                        />
                                        <YAxis
                                            tickLine={false}
                                            axisLine={false}
                                            width={40}
                                            tick={{
                                                fontSize: 11,
                                                fill: 'var(--muted-foreground)',
                                            }}
                                        />
                                        <Tooltip
                                            content={<ChartTooltip />}
                                            cursor={{
                                                fill: 'rgba(175, 250, 1, 0.08)',
                                            }}
                                        />
                                        <Bar
                                            dataKey="volume"
                                            name={t(
                                                'member.progress.volume_kg',
                                            )}
                                            fill="#AFFA01"
                                            radius={[4, 4, 0, 0]}
                                        />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Per-exercise progress */}
                    {selected !== null && (
                        <Card>
                            <CardHeader className="flex-row items-center justify-between">
                                <CardTitle className="text-base">
                                    {t('member.progress.per_exercise')}
                                </CardTitle>
                                {selectedPr !== undefined &&
                                    selectedPr !== null && (
                                        <span className="text-xs font-semibold text-[#AFFA01]">
                                            {selectedPr.weight_kg > 0
                                                ? `${selectedPr.weight_kg}kg × ${selectedPr.reps}`
                                                : `${selectedPr.reps} reps`}
                                        </span>
                                    )}
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="-mx-1 flex scrollbar-none gap-2 overflow-x-auto px-1 pb-1">
                                    {loggedExercises.map((id) => (
                                        <button
                                            key={id}
                                            type="button"
                                            onClick={() =>
                                                setSelectedExercise(id)
                                            }
                                            className={cn(
                                                'shrink-0 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors',
                                                id === selected
                                                    ? 'border-[#AFFA01]/60 bg-[#AFFA01]/10 text-[#AFFA01]'
                                                    : 'border-[#2E2E2E] text-[#8E8E93] hover:text-[#F5F5F5]',
                                            )}
                                        >
                                            {exerciseName(
                                                exerciseSummaries,
                                                id,
                                            )}
                                        </button>
                                    ))}
                                </div>

                                {selectedSeries.length > 0 && (
                                    <div className="h-36 w-full">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <LineChart
                                                data={selectedSeries}
                                                margin={{
                                                    top: 4,
                                                    right: 4,
                                                    bottom: 0,
                                                    left: -18,
                                                }}
                                            >
                                                <CartesianGrid
                                                    vertical={false}
                                                    stroke="var(--border)"
                                                    strokeDasharray="4 4"
                                                />
                                                <XAxis
                                                    dataKey="label"
                                                    tickLine={false}
                                                    axisLine={false}
                                                    tick={{
                                                        fontSize: 10,
                                                        fill: 'var(--muted-foreground)',
                                                    }}
                                                />
                                                <YAxis
                                                    tickLine={false}
                                                    axisLine={false}
                                                    width={40}
                                                    tick={{
                                                        fontSize: 11,
                                                        fill: 'var(--muted-foreground)',
                                                    }}
                                                />
                                                <Tooltip
                                                    content={<ChartTooltip />}
                                                />
                                                <Line
                                                    type="monotone"
                                                    dataKey={
                                                        selectedWeighted
                                                            ? 'kg'
                                                            : 'reps'
                                                    }
                                                    name={
                                                        selectedWeighted
                                                            ? t(
                                                                  'member.progress.best_weight',
                                                              ) + ' (kg)'
                                                            : t(
                                                                  'member.progress.reps_chart',
                                                              )
                                                    }
                                                    stroke="#AFFA01"
                                                    strokeWidth={2}
                                                    dot={{
                                                        r: 3,
                                                        fill: '#AFFA01',
                                                    }}
                                                    connectNulls
                                                />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    </div>
                                )}

                                {selectedHistory.length > 0 && (
                                    <div>
                                        <p className="mb-1.5 text-xs font-semibold tracking-wider text-[#A1A1AA] uppercase">
                                            {t(
                                                'member.progress.recent_history',
                                            )}
                                        </p>
                                        <div className="divide-y divide-[#2E2E2E]">
                                            {selectedHistory.map((session) => (
                                                <div
                                                    key={session.id}
                                                    className="flex items-center justify-between gap-3 py-2 text-sm"
                                                >
                                                    <span className="shrink-0 text-[#8E8E93]">
                                                        {formatDay(
                                                            session.date,
                                                        )}
                                                    </span>
                                                    <span className="truncate text-right font-medium text-[#F5F5F5]">
                                                        {setSummary(
                                                            setsForExercise(
                                                                session,
                                                                selected,
                                                            ),
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => setLogTarget(selected)}
                                >
                                    <NotebookPen className="size-4" />
                                    {t('member.progress.log_set')}
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    {/* PR board */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('member.progress.personal_records')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {prEntries.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('member.progress.pr_empty')}
                                </p>
                            ) : (
                                <div className="divide-y divide-[#2E2E2E]">
                                    {prEntries.map(([exerciseId, pr]) => (
                                        <div
                                            key={exerciseId}
                                            className="flex items-center justify-between gap-3 py-2 text-sm"
                                        >
                                            <span className="truncate font-medium text-[#F5F5F5]">
                                                {exerciseName(
                                                    exerciseSummaries,
                                                    exerciseId,
                                                )}
                                            </span>
                                            <span className="shrink-0 font-semibold text-[#AFFA01]">
                                                {pr.weight_kg > 0
                                                    ? `${pr.weight_kg}kg × ${pr.reps}`
                                                    : `${pr.reps} reps`}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Workout history */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('member.progress.history')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="divide-y divide-[#2E2E2E]">
                                {loggedSessions.map((session) => {
                                    const playlist = playlists.find(
                                        (p) =>
                                            p.id ===
                                            session.exercise_playlist_id,
                                    );
                                    return (
                                        <button
                                            key={session.id}
                                            type="button"
                                            onClick={() =>
                                                setActiveSessionId(session.id)
                                            }
                                            className="flex w-full items-center justify-between gap-3 py-3 text-left transition-colors hover:text-[#AFFA01]"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                                                    {playlist?.name ??
                                                        t(
                                                            'member.progress.free_session',
                                                        )}
                                                </p>
                                                <p className="mt-0.5 text-xs text-[#8E8E93]">
                                                    {formatDay(session.date)} ·{' '}
                                                    {t(
                                                        'member.progress.exercises_count',
                                                        {
                                                            count: sessionExerciseCount(
                                                                session,
                                                            ),
                                                        },
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <span className="text-xs font-semibold text-[#AFFA01]">
                                                    {Math.round(
                                                        sessionVolume(session),
                                                    ).toLocaleString()}{' '}
                                                    kg
                                                </span>
                                                <ChevronRight className="size-4 text-[#8E8E93]" />
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Body metrics */}
            <div className="mt-4 space-y-4">
                <h2 className="text-xs font-bold tracking-wider text-[#AFFA01] uppercase">
                    {t('member.progress.body_metrics')}
                </h2>
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

            <QuickLogSet
                slug={logTarget ?? ''}
                name={
                    logTarget === null
                        ? ''
                        : exerciseName(exerciseSummaries, logTarget)
                }
                equipment={
                    logTarget === null
                        ? ''
                        : (exerciseSummaries[logTarget]?.equipment ?? '')
                }
                sessions={sessions}
                open={logTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setLogTarget(null);
                    }
                }}
                onLogged={(session) =>
                    setSessions((list) =>
                        list.some((s) => s.id === session.id)
                            ? list.map((s) =>
                                  s.id === session.id ? session : s,
                              )
                            : [session, ...list],
                    )
                }
            />
        </MobileShell>
    );
}
