import { Head, Link, usePage } from '@inertiajs/react';
import {
    Camera,
    ChevronRight,
    Flame,
    NotebookPen,
    TrendingUp,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
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
    type BodyPart,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import { ExerciseSearchDialog } from '@/components/member/exercise-search-dialog';

import {
    MeasurementsCard,
    type MeasurementEntry,
} from '@/components/member/measurements-card';
import { MobileShell } from '@/components/member/mobile-shell';
import {
    MuscleRadarCard,
    type MuscleVolume,
} from '@/components/member/muscle-radar-card';
import {
    PhotosCard,
    uploadProgressPhoto,
    type ProgressPhotoEntry,
} from '@/components/member/photos-card';
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
    volumeByBodyPart,
    weeklyStats,
    weeklyVolumeSeries,
} from '@/components/member/workout-progress';
import { WorkoutSessionCard } from '@/components/member/workout-session';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { exercise, history, records, today } from '@/routes/member';

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
    exerciseSummaries: initialSummaries,
    weightLogs: initialWeightLogs,
    measurements: initialMeasurements,
    photos: initialPhotos,
    bodyParts,
    equipment,
    streak,
    activityWeek,
    muscleVolumes,
}: {
    playlists: ExercisePlaylist[];
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
    weightLogs: WeightLogEntry[];
    measurements: MeasurementEntry[];
    photos: ProgressPhotoEntry[];
    bodyParts: BodyPart[];
    equipment: string[];
    streak: number;
    activityWeek: ActivityDay[];
    muscleVolumes: MuscleVolume[];
}) {
    const { url } = usePage();
    const { t } = useTranslation();
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [exerciseSummaries, setExerciseSummaries] =
        useState<ExerciseSummaries>(initialSummaries);
    const [weightLogs, setWeightLogs] =
        useState<WeightLogEntry[]>(initialWeightLogs);
    const [measurements, setMeasurements] =
        useState<MeasurementEntry[]>(initialMeasurements);
    const [photos, setPhotos] = useState<ProgressPhotoEntry[]>(initialPhotos);
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
    const [logTarget, setLogTarget] = useState<{
        slug: string;
        name: string;
        equipment: string;
        image_url?: string | null;
    } | null>(null);
    const [searchOpen, setSearchOpen] = useState(
        () => new URLSearchParams(url.split('?')[1] ?? '').get('log') === '1',
    );
    const [metric, setMetric] = useState<'weight' | 'e1rm'>('weight');
    const photoInputRef = useRef<HTMLInputElement>(null);
    const [photoBusy, setPhotoBusy] = useState(false);

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
                  e1rm: p.e1rm,
                  reps: p.reps,
              }));
    const selectedPr = selected === null ? null : prs[selected];
    const selectedHistory =
        selected === null
            ? []
            : exerciseHistory(sessions, selected, undefined, 3);

    const volumeSeries = weeklyVolumeSeries(sessions, 10);
    const weekDelta = weeklyStats(sessions);
    const muscleSplit = volumeByBodyPart(sessions, exerciseSummaries).slice(
        0,
        6,
    );
    const muscleMax = muscleSplit[0]?.volume ?? 0;

    /**
     * Translated body-part name, falling back to the raw catalog value
     * when no translation line exists.
     */
    function partLabel(part: string): string {
        const key = `exercise_values.body_part.${part}`;
        const text = t(key);
        return text === key ? part : text;
    }

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

            {/* Quick actions */}
            <div className="mt-4 grid grid-cols-2 gap-2.5">
                <Button
                    type="button"
                    onClick={() => setSearchOpen(true)}
                    className="bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black hover:brightness-105"
                >
                    <NotebookPen className="size-4" />
                    {t('member.progress.log_set')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={photoBusy}
                    onClick={() => photoInputRef.current?.click()}
                >
                    <Camera className="size-4" />
                    {t('member.progress.add_photo')}
                </Button>
            </div>

            <input
                ref={photoInputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={async (e) => {
                    const file = e.target.files?.[0];
                    e.target.value = '';
                    if (!file || photoBusy) {
                        return;
                    }
                    setPhotoBusy(true);
                    try {
                        const photo = await uploadProgressPhoto(file);
                        setPhotos((list) => [photo, ...list]);
                    } catch {
                        toast.error(t('member.progress.photo_failed'));
                    } finally {
                        setPhotoBusy(false);
                    }
                }}
            />

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

                    <p className="text-center text-xs text-[#8E8E93]">
                        {t('member.progress.vs_last_week', {
                            sessions: weekDelta.previous.sessions,
                            volume: Math.round(
                                weekDelta.previous.volume,
                            ).toLocaleString(),
                        })}
                    </p>

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

                    {/* Progress photos */}
                    <PhotosCard photos={photos} onChange={setPhotos} />

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

                    {/* Muscle split */}
                    {muscleSplit.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('member.progress.muscle_split')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {muscleSplit.map(({ part, volume }) => (
                                        <div
                                            key={part}
                                            className="flex items-center gap-3"
                                        >
                                            <span className="w-20 shrink-0 truncate text-xs font-medium text-[#8E8E93] capitalize">
                                                {partLabel(part)}
                                            </span>
                                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-[#2E2E2E]">
                                                <div
                                                    className="h-full rounded-full bg-[#AFFA01]"
                                                    style={{
                                                        width: `${Math.max(4, (volume / muscleMax) * 100)}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="w-14 shrink-0 text-right text-xs font-semibold text-[#F5F5F5]">
                                                {Math.round(
                                                    volume,
                                                ).toLocaleString()}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

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

                                {selectedWeighted && (
                                    <div className="flex gap-1.5">
                                        {(['weight', 'e1rm'] as const).map(
                                            (m) => (
                                                <button
                                                    key={m}
                                                    type="button"
                                                    onClick={() => setMetric(m)}
                                                    className={cn(
                                                        'rounded-lg border px-2.5 py-1 text-[10px] font-bold tracking-wider uppercase transition-colors',
                                                        metric === m
                                                            ? 'border-[#AFFA01]/60 bg-[#AFFA01]/10 text-[#AFFA01]'
                                                            : 'border-[#2E2E2E] text-[#8E8E93]',
                                                    )}
                                                >
                                                    {m === 'weight'
                                                        ? 'kg'
                                                        : 'e1RM'}
                                                </button>
                                            ),
                                        )}
                                    </div>
                                )}

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
                                                            ? metric === 'e1rm'
                                                                ? 'e1rm'
                                                                : 'kg'
                                                            : 'reps'
                                                    }
                                                    name={
                                                        selectedWeighted
                                                            ? metric === 'e1rm'
                                                                ? 'e1RM (kg)'
                                                                : t(
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
                                    onClick={() =>
                                        setLogTarget({
                                            slug: selected,
                                            name: exerciseName(
                                                exerciseSummaries,
                                                selected,
                                            ),
                                            equipment:
                                                exerciseSummaries[selected]
                                                    ?.equipment ?? '',
                                            image_url:
                                                exerciseSummaries[selected]
                                                    ?.image_url ?? null,
                                        })
                                    }
                                >
                                    <NotebookPen className="size-4" />
                                    {t('member.progress.log_set')}
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    {/* PR board — top records preview, full board on /progress/records */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">
                                {t('member.progress.personal_records')}
                            </CardTitle>
                            {prEntries.length > 0 && (
                                <Link
                                    href={records().url}
                                    className="flex items-center gap-1 text-xs font-semibold text-[#AFFA01] transition-colors hover:brightness-110"
                                >
                                    {t('member.records.view_all', {
                                        count: prEntries.length,
                                    })}
                                    <ChevronRight className="size-3.5" />
                                </Link>
                            )}
                        </CardHeader>
                        <CardContent>
                            {prEntries.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('member.progress.pr_empty')}
                                </p>
                            ) : (
                                <div className="divide-y divide-[#2E2E2E]">
                                    {prEntries
                                        .slice(0, 3)
                                        .map(([exerciseId, pr]) => (
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

                    {/* Workout history — recent sessions, full list on /progress/history */}
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">
                                {t('member.progress.history')}
                            </CardTitle>
                            {loggedSessions.length > 0 && (
                                <Link
                                    href={history().url}
                                    className="flex items-center gap-1 text-xs font-semibold text-[#AFFA01] transition-colors hover:brightness-110"
                                >
                                    {t('member.records.view_all', {
                                        count: loggedSessions.length,
                                    })}
                                    <ChevronRight className="size-3.5" />
                                </Link>
                            )}
                        </CardHeader>
                        <CardContent>
                            <div className="divide-y divide-[#2E2E2E]">
                                {loggedSessions.slice(0, 3).map((session) => {
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
                <MuscleRadarCard volumes={muscleVolumes} />
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
                <MeasurementsCard
                    measurements={measurements}
                    onLogged={(entry) =>
                        setMeasurements((list) => [
                            entry,
                            ...list.filter(
                                (m) => m.logged_on !== entry.logged_on,
                            ),
                        ])
                    }
                />
                <BmiCard />
                <BodyFatCard />
            </div>

            {/* Today's log / active session */}
            <div className="mt-4">
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
            </div>

            <ExerciseSearchDialog
                open={searchOpen}
                onOpenChange={setSearchOpen}
                sessions={sessions}
                exerciseSummaries={exerciseSummaries}
                bodyParts={bodyParts}
                equipment={equipment}
                onPick={(picked) => {
                    setExerciseSummaries((map) => ({
                        ...map,
                        [picked.slug]: {
                            name: picked.name,
                            detail: picked.detail,
                            equipment: picked.equipment,
                            body_part: picked.body_part,
                            image_url: picked.image_url,
                        },
                    }));
                    setLogTarget({
                        slug: picked.slug,
                        name: picked.name,
                        equipment: picked.equipment,
                        image_url: picked.image_url,
                    });
                }}
            />

            <QuickLogSet
                slug={logTarget?.slug ?? ''}
                name={logTarget?.name ?? ''}
                equipment={logTarget?.equipment ?? ''}
                imageUrl={logTarget?.image_url ?? null}
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
