import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    exerciseDetail,
    exerciseName,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import { MobileShell } from '@/components/member/mobile-shell';
import { SessionStart } from '@/components/member/session-start';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import { WorkoutSessionCard } from '@/components/member/workout-session';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { show as exerciseShow } from '@/routes/member/exercises';

function formatLastTrained(lastTrainedOn: string | null): string {
    if (lastTrainedOn === null) {
        return 'not trained recently';
    }
    const days = Math.max(
        0,
        Math.round(
            (Date.now() - new Date(`${lastTrainedOn}T12:00:00`).getTime()) /
                86_400_000,
        ),
    );
    if (days === 0) {
        return 'trained today';
    }
    if (days === 1) {
        return 'last trained yesterday';
    }
    return `last trained ${days} days ago`;
}

export default function MemberToday({
    playlist,
    muscleFocus,
    playlists,
    sessions: initialSessions,
    exerciseSummaries,
}: {
    playlist: ExercisePlaylist;
    muscleFocus: {
        muscle: string | null;
        label: string | null;
        lastTrainedOn: string | null;
    };
    playlists: ExercisePlaylist[];
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
}) {
    const { url } = usePage();
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [activeSessionId, setActiveSessionId] = useState<number | null>(
        () => {
            const param = new URLSearchParams(
                url.split('?')[1] ?? '',
            ).get('session');
            return param === null ? null : Number(param);
        },
    );
    const [expandedId, setExpandedId] = useState<string | null>(null);

    const todayKey = new Date().toISOString().slice(0, 10);
    const todaysSessions = sessions.filter((s) => s.date === todayKey);
    const activeSession =
        sessions.find((s) => s.id === activeSessionId) ?? null;
    const activePlaylist =
        playlists.find((p) => p.id === activeSession?.exercise_playlist_id) ??
        null;

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
            <Head title="Today" />
            <h1 className="text-xl font-semibold tracking-tight">
                Today's workout
            </h1>

            <Card className="mt-4 p-0 overflow-hidden">
                <CardHeader className="p-5 border-b border-[#2E2E2E]">
                    <CardTitle className="text-lg font-bold text-[#F5F5F5]">{playlist.name}</CardTitle>
                    {muscleFocus.label !== null && (
                        <p className="text-xs font-semibold text-[#AFFA01]">
                            {muscleFocus.label} ·{' '}
                            {formatLastTrained(muscleFocus.lastTrainedOn)}
                        </p>
                    )}
                </CardHeader>
                <CardContent className="divide-y divide-[#2E2E2E] p-0">
                    {playlist.exercise_ids.map((id, index) => {
                        const expanded = expandedId === id;
                        return (
                            <div
                                key={id}
                                className="flex items-center gap-3 px-5 py-3.5 transition-colors hover:bg-[#252525]"
                            >
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-[#141414] border border-[#2E2E2E] text-xs font-bold text-[#8E8E93]">
                                    {index + 1}
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setExpandedId(expanded ? null : id)
                                    }
                                    className="min-w-0 flex-1 text-left"
                                >
                                    <p className="text-sm font-semibold text-[#F5F5F5]">
                                        {exerciseName(exerciseSummaries, id)}
                                    </p>
                                    <p className="text-xs text-[#8E8E93]">
                                        {exerciseDetail(
                                            exerciseSummaries,
                                            id,
                                        ) ?? ''}
                                    </p>
                                </button>
                                {expanded && (
                                    <Link
                                        href={exerciseShow(id).url}
                                        aria-label={`Instructions for ${exerciseName(exerciseSummaries, id)}`}
                                        className="flex shrink-0 items-center gap-1 text-xs font-semibold text-[#AFFA01] hover:underline"
                                    >
                                        Instructions
                                        <ChevronRight className="size-3" />
                                    </Link>
                                )}
                            </div>
                        );
                    })}
                </CardContent>
            </Card>

            <div className="mt-4">
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
        </MobileShell>
    );
}
