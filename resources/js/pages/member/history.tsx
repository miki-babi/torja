import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, ChevronRight, Dumbbell } from 'lucide-react';
import { useState } from 'react';
import type { ExerciseSummaries } from '@/components/member/exercise-data';
import { LogPastWorkoutDialog } from '@/components/member/log-past-workout-dialog';
import { MobileShell } from '@/components/member/mobile-shell';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import {
    sessionExerciseCount,
    sessionVolume,
} from '@/components/member/workout-progress';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { progress } from '@/routes/member';

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

export default function MemberHistory({
    sessions: initialSessions,
    playlists,
    exerciseSummaries,
    bodyParts,
    equipment,
}: {
    sessions: WorkoutSession[];
    playlists: ExercisePlaylist[];
    exerciseSummaries: ExerciseSummaries;
    bodyParts: { id: string; label: string; count?: number }[];
    equipment: string[];
}) {
    const { t } = useTranslation();
    const [sessions, setSessions] = useState(initialSessions);
    const [backfillOpen, setBackfillOpen] = useState(false);

    const loggedSessions = sessions.filter((s) => s.sets.length > 0);

    return (
        <MobileShell>
            <Head title={t('member.progress.history')} />

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
                    {t('member.progress.history')}
                </h1>
                {loggedSessions.length > 0 && (
                    <span className="ml-auto rounded-full border border-[#AFFA01]/30 bg-[#AFFA01]/10 px-2.5 py-0.5 text-xs font-bold text-[#AFFA01]">
                        {loggedSessions.length}
                    </span>
                )}
            </div>

            <Button
                type="button"
                variant="outline"
                className="mt-4 w-full border-[#2E2E2E] bg-[#1F1F1F] text-xs font-bold text-[#A1A1AA] hover:border-[#AFFA01]/40 hover:text-[#AFFA01]"
                onClick={() => setBackfillOpen(true)}
            >
                <CalendarClock className="size-4" />
                {t('member.progress.log_past')}
            </Button>

            {loggedSessions.length === 0 ? (
                <div className="mt-6 flex flex-col items-center gap-2 rounded-2xl border border-[#2E2E2E] bg-[#262626] p-8 text-center">
                    <Dumbbell className="size-7 text-[#AFFA01]" />
                    <p className="text-sm text-muted-foreground">
                        {t('member.history.empty')}
                    </p>
                </div>
            ) : (
                <div className="mt-4 divide-y divide-[#2E2E2E] rounded-3xl border border-[#2E2E2E] bg-[#1F1F1F] px-4">
                    {loggedSessions.map((session) => {
                        const playlist = playlists.find(
                            (p) => p.id === session.exercise_playlist_id,
                        );
                        return (
                            <Link
                                key={session.id}
                                href={
                                    progress({
                                        query: { session: session.id },
                                    }).url
                                }
                                className="flex w-full items-center justify-between gap-3 py-3 text-left transition-colors hover:text-[#AFFA01]"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                                        {playlist?.name ??
                                            t('member.progress.free_session')}
                                    </p>
                                    <p className="mt-0.5 text-xs text-[#8E8E93]">
                                        {formatDay(session.date)} ·{' '}
                                        {t('member.progress.exercises_count', {
                                            count: sessionExerciseCount(
                                                session,
                                            ),
                                        })}
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
                            </Link>
                        );
                    })}
                </div>
            )}

            <LogPastWorkoutDialog
                open={backfillOpen}
                onOpenChange={setBackfillOpen}
                exerciseSummaries={exerciseSummaries}
                sessions={sessions}
                bodyParts={bodyParts}
                equipment={equipment}
                onLogged={(session) =>
                    setSessions((list) =>
                        [
                            session,
                            ...list.filter((s) => s.id !== session.id),
                        ].sort((a, b) => (a.date < b.date ? 1 : -1)),
                    )
                }
            />
        </MobileShell>
    );
}
