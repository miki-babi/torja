import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Play } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { ExerciseSummaries } from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { SessionSummary } from '@/components/member/session-summary';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import { WorkoutSessionCard } from '@/components/member/workout-session';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { exercise } from '@/routes/member';
import { show as playlistShow } from '@/routes/member/playlists';
import { store as storeSession } from '@/routes/member/sessions';

export default function MemberProgramShow({
    program,
    workouts,
    sessions: initialSessions,
    exerciseSummaries,
    viewSourceUrl,
}: {
    program: { id: number; name: string; image_url: string | null };
    workouts: ExercisePlaylist[];
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
    viewSourceUrl: string | null;
}) {
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [sessionDismissed, setSessionDismissed] = useState(false);

    const todayKey = new Date().toISOString().slice(0, 10);
    const playlistIds = new Set(workouts.map((p) => p.id));
    const todaysSession =
        sessions.find(
            (s) =>
                s.date === todayKey &&
                s.exercise_playlist_id !== null &&
                playlistIds.has(s.exercise_playlist_id),
        ) ?? null;
    const activeSession = sessionDismissed ? null : todaysSession;
    const activePlaylist =
        workouts.find((p) => p.id === activeSession?.exercise_playlist_id) ??
        null;

    function upsertSession(next: WorkoutSession) {
        setSessions((list) =>
            list.some((s) => s.id === next.id)
                ? list.map((s) => (s.id === next.id ? next : s))
                : [next, ...list],
        );
    }

    async function startSession(playlist: ExercisePlaylist) {
        if (busyId !== null) {
            return;
        }
        setBusyId(playlist.id);
        try {
            const { session } = await memberApi<{ session: WorkoutSession }>(
                storeSession().url,
                'POST',
                { exercise_playlist_id: playlist.id },
            );
            upsertSession(session);
            setSessionDismissed(false);
        } catch {
            toast.error('Could not start session');
        } finally {
            setBusyId(null);
        }
    }

    return (
        <MobileShell>
            <Head title={program.name} />
            <div className="flex items-center justify-between">
                <Button variant="ghost" size="sm" asChild className="-ml-2">
                    <Link href={exercise()}>
                        <ChevronLeft className="size-4" />
                        Exercise
                    </Link>
                </Button>
                <Badge variant="secondary">program</Badge>
            </div>

            <h1 className="mt-2 text-xl font-semibold tracking-tight">
                {program.name}
            </h1>
            <p className="text-sm text-muted-foreground">
                {workouts.length} workout{workouts.length === 1 ? '' : 's'}
            </p>

            {activeSession !== null && activePlaylist !== null ? (
                <div className="mt-4">
                    <WorkoutSessionCard
                        session={activeSession}
                        playlist={activePlaylist}
                        sessions={sessions}
                        exerciseSummaries={exerciseSummaries}
                        onSessionChange={upsertSession}
                        onBack={() => setSessionDismissed(true)}
                    />
                </div>
            ) : (
                <>
                    <Card className="mt-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Workouts
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y p-0">
                            {workouts.length === 0 && (
                                <p className="px-6 py-3 text-sm text-muted-foreground">
                                    No workouts copied yet.
                                </p>
                            )}
                            {workouts.map((playlist, index) => {
                                const existing =
                                    sessions.find(
                                        (s) =>
                                            s.date === todayKey &&
                                            s.exercise_playlist_id ===
                                                playlist.id,
                                    ) ?? null;
                                return (
                                    <div
                                        key={playlist.id}
                                        className="flex items-center gap-3 px-6 py-3"
                                    >
                                        <span className="w-5 text-center text-xs text-muted-foreground tabular-nums">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={
                                                    playlistShow(playlist.id)
                                                        .url
                                                }
                                                className="block truncate text-sm font-medium hover:underline"
                                            >
                                                {playlist.workout_title ??
                                                    playlist.name}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {playlist.exercise_ids.length}{' '}
                                                exercise
                                                {playlist.exercise_ids
                                                    .length === 1
                                                    ? ''
                                                    : 's'}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant={
                                                existing === null
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className={
                                                existing === null
                                                    ? 'shrink-0 bg-lime-400 text-lime-950 hover:bg-lime-500'
                                                    : 'shrink-0'
                                            }
                                            disabled={busyId !== null}
                                            onClick={() =>
                                                startSession(playlist)
                                            }
                                        >
                                            <Play className="size-3.5" />
                                            {existing === null
                                                ? 'Start'
                                                : `Continue · ${existing.sets.length}`}
                                        </Button>
                                        <Link
                                            href={playlistShow(playlist.id).url}
                                            aria-label={`View ${playlist.workout_title ?? playlist.name}`}
                                            className="flex size-7 shrink-0 items-center justify-center rounded-full border text-muted-foreground"
                                        >
                                            <ChevronRight className="size-4" />
                                        </Link>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    {viewSourceUrl !== null && (
                        <Button
                            variant="link"
                            size="sm"
                            asChild
                            className="mt-2 px-0"
                        >
                            <a href={viewSourceUrl}>View program details</a>
                        </Button>
                    )}
                </>
            )}

            <div className="mt-4">
                <SessionSummary
                    sessions={sessions}
                    exerciseSummaries={exerciseSummaries}
                />
            </div>
        </MobileShell>
    );
}
