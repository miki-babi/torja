import { router, usePage } from '@inertiajs/react';
import { ChevronLeft, History, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    exerciseName,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import type {
    ExercisePlaylist,
    WorkoutSession,
    WorkoutSetEntry,
} from '@/components/member/types';
import {
    exerciseHistory,
    lastSessionWithExercise,
    setsForExercise,
    setSummary,
} from '@/components/member/workout-progress';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { exercises as sessionExercises } from '@/routes/member/sessions';
import { destroy, upsert } from '@/routes/member/sessions/sets';

function parseOptional(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }
    const parsed = Number.parseFloat(value);
    return Number.isNaN(parsed) ? null : parsed;
}

function SetRow({
    set,
    index,
    onSave,
    onRemove,
}: {
    set: WorkoutSetEntry;
    index: number;
    onSave: (weight: number | null, reps: number | null) => void;
    onRemove: () => void;
}) {
    const [weight, setWeight] = useState(
        set.weight_kg === null ? '' : String(set.weight_kg),
    );
    const [reps, setReps] = useState(set.reps === null ? '' : String(set.reps));

    function commit() {
        onSave(parseOptional(weight), parseOptional(reps));
    }

    return (
        <div className="flex items-center gap-2">
            <span className="w-6 text-center text-xs text-muted-foreground">
                {index + 1}
            </span>
            <Input
                type="number"
                inputMode="decimal"
                min="0"
                step="any"
                value={weight}
                onChange={(e) => setWeight(e.target.value)}
                onBlur={commit}
                placeholder="kg"
                aria-label={`Set ${index + 1} weight in kg`}
                className="h-8 w-20"
            />
            <span className="text-xs text-muted-foreground">×</span>
            <Input
                type="number"
                inputMode="numeric"
                min="0"
                value={reps}
                onChange={(e) => setReps(e.target.value)}
                onBlur={commit}
                placeholder="reps"
                aria-label={`Set ${index + 1} reps`}
                className="h-8 w-16"
            />
            <Button
                variant="ghost"
                size="icon"
                className="ml-auto size-7"
                onClick={onRemove}
                aria-label={`Remove set ${index + 1}`}
            >
                <X className="size-4" />
            </Button>
        </div>
    );
}

export function WorkoutSessionCard({
    session,
    playlist,
    sessions,
    exerciseSummaries,
    onSessionChange,
    onBack,
}: {
    session: WorkoutSession;
    playlist: ExercisePlaylist | null;
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
    onSessionChange: (session: WorkoutSession) => void;
    onBack: () => void;
}) {
    const [historyFor, setHistoryFor] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const { url } = usePage();

    const playlistIds = playlist?.exercise_ids ?? [];
    const extraIds = [
        ...new Set(session.sets.map((s) => s.exercise_id)),
    ].filter((id) => !playlistIds.includes(id));
    const exerciseIds = [...playlistIds, ...extraIds];

    async function upsertSet(
        exerciseId: string,
        setIndex: number,
        weight: number | null,
        reps: number | null,
    ) {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const { session: updated } = await memberApi<{
                session: WorkoutSession;
            }>(upsert(session.id).url, 'PUT', {
                exercise_id: exerciseId,
                set_index: setIndex,
                weight_kg: weight,
                reps,
            });
            onSessionChange(updated);
        } catch {
            toast.error('Could not save set');
        } finally {
            setBusy(false);
        }
    }

    async function removeSet(setId: number) {
        try {
            const { session: updated } = await memberApi<{
                session: WorkoutSession;
            }>(destroy({ session: session.id, set: setId }).url, 'DELETE');
            onSessionChange(updated);
        } catch {
            toast.error('Could not remove set');
        }
    }

    function openAddExercise() {
        router.visit(
            `${sessionExercises(session.id).url}?back=${encodeURIComponent(url)}`,
        );
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0"
                    onClick={onBack}
                    aria-label="Back to sessions"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <div className="min-w-0 flex-1">
                    <CardTitle className="truncate text-base">
                        {playlist?.name ?? 'Free session'}
                    </CardTitle>
                    <p className="text-xs text-muted-foreground">
                        {session.sets.length} set
                        {session.sets.length === 1 ? '' : 's'} logged
                    </p>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                {exerciseIds.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No exercises yet — add one below.
                    </p>
                )}

                {exerciseIds.map((exerciseId) => {
                    const exerciseNameFor = exerciseName(
                        exerciseSummaries,
                        exerciseId,
                    );
                    const sets = setsForExercise(session, exerciseId);
                    const last = lastSessionWithExercise(
                        sessions,
                        exerciseId,
                        session.id,
                    );
                    const lastSummary = last
                        ? setSummary(setsForExercise(last, exerciseId))
                        : '';
                    const history = exerciseHistory(
                        sessions,
                        exerciseId,
                        session.id,
                    );
                    const showHistory = historyFor === exerciseId;

                    return (
                        <div key={exerciseId}>
                            <div className="flex items-center gap-2">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">
                                        {exerciseNameFor}
                                    </p>
                                    {lastSummary !== '' && (
                                        <p className="text-xs text-muted-foreground">
                                            Last: {lastSummary}
                                        </p>
                                    )}
                                </div>
                                {history.length > 0 && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className={cn(
                                            'size-7 shrink-0',
                                            showHistory &&
                                                'bg-lime-300/40 text-lime-900',
                                        )}
                                        onClick={() =>
                                            setHistoryFor(
                                                showHistory ? null : exerciseId,
                                            )
                                        }
                                        aria-label={`History for ${exerciseNameFor}`}
                                    >
                                        <History className="size-4" />
                                    </Button>
                                )}
                            </div>

                            <div className="mt-2 space-y-2 pl-1">
                                {sets.map((set, i) => (
                                    <SetRow
                                        key={set.id}
                                        set={set}
                                        index={i}
                                        onSave={(weight, reps) =>
                                            upsertSet(
                                                exerciseId,
                                                set.set_index,
                                                weight,
                                                reps,
                                            )
                                        }
                                        onRemove={() => removeSet(set.id)}
                                    />
                                ))}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-7 text-xs"
                                    onClick={() =>
                                        upsertSet(
                                            exerciseId,
                                            sets.length,
                                            null,
                                            null,
                                        )
                                    }
                                >
                                    <Plus className="size-3.5" />
                                    Add set
                                </Button>
                            </div>

                            {showHistory && (
                                <div className="mt-2 rounded-md bg-muted/50 p-2.5 pl-3">
                                    {history.map((h) => (
                                        <div
                                            key={h.id}
                                            className="flex justify-between py-0.5 text-xs"
                                        >
                                            <span className="text-muted-foreground">
                                                {new Date(
                                                    `${h.date}T12:00:00`,
                                                ).toLocaleDateString(
                                                    undefined,
                                                    {
                                                        month: 'short',
                                                        day: 'numeric',
                                                    },
                                                )}
                                            </span>
                                            <span>
                                                {setSummary(
                                                    setsForExercise(
                                                        h,
                                                        exerciseId,
                                                    ),
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    );
                })}

                <div className="flex gap-2 pt-1">
                    <Button
                        variant="outline"
                        className="flex-1"
                        onClick={openAddExercise}
                    >
                        <Plus className="size-4" />
                        Add exercise
                    </Button>
                    <Button
                        className="flex-1 bg-lime-400 text-lime-950 hover:bg-lime-500"
                        onClick={onBack}
                    >
                        Done
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
