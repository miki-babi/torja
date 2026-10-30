import { ChevronRight } from 'lucide-react';
import {
    exerciseName,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import {
    setsForExercise,
    setSummary,
} from '@/components/member/workout-progress';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * Today's logged exercises grouped by session. Each block opens the session
 * so sets can be edited. Renders nothing when nothing is logged yet.
 */
export function TodaysLog({
    sessions,
    playlists,
    exerciseSummaries,
    onOpen,
}: {
    sessions: WorkoutSession[];
    playlists: ExercisePlaylist[];
    exerciseSummaries: ExerciseSummaries;
    onOpen: (session: WorkoutSession) => void;
}) {
    const logged = sessions.filter((s) => s.sets.length > 0);

    if (logged.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Logged today</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {logged.map((session) => {
                    const playlist = playlists.find(
                        (p) => p.id === session.exercise_playlist_id,
                    );
                    const exerciseIds = [
                        ...new Set(session.sets.map((s) => s.exercise_id)),
                    ];

                    return (
                        <button
                            key={session.id}
                            type="button"
                            onClick={() => onOpen(session)}
                            className="w-full rounded-2xl border border-[#2E2E2E] bg-[#1A1A1A] p-3.5 text-left transition-all hover:border-[#AFFA01]/40"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <p className="truncate text-xs font-bold tracking-wider text-[#8E8E93] uppercase">
                                    {playlist?.name ?? 'Free session'}
                                </p>
                                <span className="flex shrink-0 items-center gap-1 text-xs text-[#8E8E93]">
                                    {session.sets.length} set
                                    {session.sets.length === 1 ? '' : 's'}
                                    <ChevronRight className="size-3.5" />
                                </span>
                            </div>
                            <div className="mt-2 divide-y divide-[#2E2E2E]">
                                {exerciseIds.map((id) => (
                                    <div
                                        key={id}
                                        className="flex items-center justify-between gap-3 py-1.5 text-sm"
                                    >
                                        <span className="min-w-0 flex-1 truncate font-medium text-[#F5F5F5]">
                                            {exerciseName(
                                                exerciseSummaries,
                                                id,
                                            )}
                                        </span>
                                        <span className="shrink-0 text-xs font-semibold text-[#AFFA01]">
                                            {setSummary(
                                                setsForExercise(session, id),
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </button>
                    );
                })}
            </CardContent>
        </Card>
    );
}
