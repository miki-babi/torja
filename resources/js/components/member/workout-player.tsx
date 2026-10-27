import { Check, ChevronLeft, ChevronRight, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { ExerciseSummary } from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import type {
    WorkoutSession,
    WorkoutSetEntry,
} from '@/components/member/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { destroy, upsert } from '@/routes/member/sessions/sets';

export const DEFAULT_SETS = 3;
export const DEFAULT_REPS = 10;

export type PlayerExercise = ExerciseSummary & {
    gif_url: string;
    sets: number | null;
    reps: number | null;
};

export type PlayerWorkout = {
    id: number;
    title: string;
    exercises: PlayerExercise[];
};

type Translate = (
    key: string,
    params?: Record<string, string | number>,
) => string;

function parseOptional(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }
    const parsed = Number.parseFloat(value);
    return Number.isNaN(parsed) ? null : parsed;
}

function PlayerSetRow({
    position,
    logged,
    targetReps,
    onSave,
    onRemove,
}: {
    position: number;
    logged: WorkoutSetEntry | null;
    targetReps: number;
    onSave: (weight: number | null, reps: number | null) => void;
    onRemove: () => void;
}) {
    const [weight, setWeight] = useState(
        logged?.weight_kg == null ? '' : String(logged.weight_kg),
    );
    const [reps, setReps] = useState(
        logged?.reps == null ? '' : String(logged.reps),
    );

    function commit() {
        const nextWeight = parseOptional(weight);
        const nextReps = parseOptional(reps);
        if (nextWeight === null && nextReps === null) {
            return;
        }
        if (
            nextWeight === (logged?.weight_kg ?? null) &&
            nextReps === (logged?.reps ?? null)
        ) {
            return;
        }
        onSave(nextWeight, nextReps);
    }

    return (
        <div className="flex items-center gap-2">
            <span
                className={cn(
                    'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-medium',
                    logged !== null
                        ? 'bg-lime-400 text-lime-950'
                        : 'bg-muted text-muted-foreground',
                )}
            >
                {logged !== null ? <Check className="size-3.5" /> : position}
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
                aria-label={`Set ${position} weight in kg`}
                className="h-9 flex-1 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            />
            <span className="text-xs text-muted-foreground">×</span>
            <Input
                type="number"
                inputMode="numeric"
                min="0"
                value={reps}
                onChange={(e) => setReps(e.target.value)}
                onBlur={commit}
                placeholder={String(targetReps)}
                aria-label={`Set ${position} reps`}
                className="h-9 flex-1 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            />
            <Button
                variant="ghost"
                size="icon"
                className="size-7 shrink-0 text-muted-foreground"
                onClick={onRemove}
                aria-label={`Remove set ${position}`}
            >
                <X className="size-4" />
            </Button>
        </div>
    );
}

export function WorkoutPlayer({
    workout,
    session,
    onSessionChange,
    onExit,
    t,
}: {
    workout: PlayerWorkout;
    session: WorkoutSession;
    onSessionChange: (session: WorkoutSession) => void;
    onExit: () => void;
    t: Translate;
}) {
    const [index, setIndex] = useState(0);
    const [extraSets, setExtraSets] = useState<Record<string, number>>({});
    const [hiddenSets, setHiddenSets] = useState<Record<string, number[]>>({});

    const total = workout.exercises.length;
    const current = workout.exercises[index] ?? null;

    function loggedFor(slug: string): WorkoutSetEntry[] {
        return session.sets.filter((set) => set.exercise_id === slug);
    }

    async function saveSet(
        slug: string,
        setIndex: number,
        weight: number | null,
        reps: number | null,
    ) {
        try {
            const { session: next } = await memberApi<{
                session: WorkoutSession;
            }>(upsert({ session: session.id }).url, 'PUT', {
                exercise_id: slug,
                set_index: setIndex,
                weight_kg: weight,
                reps,
            });
            onSessionChange(next);
        } catch {
            toast.error(t('shared_program.set_save_failed_toast'));
        }
    }

    async function removeSet(slug: string, setIndex: number) {
        const set = session.sets.find(
            (s) => s.exercise_id === slug && s.set_index === setIndex,
        );
        if (set === undefined) {
            setHiddenSets((map) => ({
                ...map,
                [slug]: [...(map[slug] ?? []), setIndex],
            }));
            return;
        }
        try {
            const { session: next } = await memberApi<{
                session: WorkoutSession;
            }>(destroy({ session: session.id, set: set.id }).url, 'DELETE');
            onSessionChange(next);
        } catch {
            toast.error(t('shared_program.set_save_failed_toast'));
        }
    }

    function finish() {
        toast.success(t('shared_program.workout_done'));
        onExit();
    }

    if (current === null) {
        return null;
    }

    const targetSets = current.sets ?? DEFAULT_SETS;
    const targetReps = current.reps ?? DEFAULT_REPS;
    const logged = loggedFor(current.slug);
    const rowCount = Math.min(
        50,
        Math.max(
            targetSets,
            logged.length === 0
                ? 0
                : Math.max(...logged.map((set) => set.set_index)) + 1,
        ) + (extraSets[current.slug] ?? 0),
    );
    const isLast = index === total - 1;

    return (
        <div className="flex min-h-[70vh] flex-col">
            <div className="flex items-center justify-between gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    className="-ml-2 size-8"
                    onClick={onExit}
                    aria-label={t('shared_program.exit_workout')}
                >
                    <X className="size-4" />
                </Button>
                <div className="min-w-0 flex-1 text-center">
                    <p className="truncate text-sm font-medium">
                        {workout.title}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('shared_program.exercise_of', {
                            current: index + 1,
                            total,
                        })}
                    </p>
                </div>
                <div className="size-8" />
            </div>

            <div className="mt-3 flex gap-1">
                {workout.exercises.map((ex, i) => (
                    <button
                        key={ex.slug}
                        type="button"
                        onClick={() => setIndex(i)}
                        aria-label={ex.name}
                        className={cn(
                            'h-1.5 flex-1 rounded-full transition-colors',
                            i === index
                                ? 'bg-lime-500'
                                : loggedFor(ex.slug).length > 0
                                  ? 'bg-lime-300 dark:bg-lime-700'
                                  : 'bg-muted',
                        )}
                    />
                ))}
            </div>

            <Card className="mt-4 overflow-hidden">
                <img
                    key={current.slug}
                    src={current.gif_url}
                    alt={`${current.name} demo`}
                    className="aspect-square w-full bg-muted object-cover"
                />
                <CardContent className="space-y-3 p-4">
                    <div>
                        <h2 className="text-lg font-semibold capitalize">
                            {current.name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {current.detail}
                        </p>
                    </div>
                    <Badge
                        variant="secondary"
                        className="bg-lime-100 text-lime-900 dark:bg-lime-900/40 dark:text-lime-200"
                    >
                        {t('shared_program.target_sets_reps', {
                            sets: targetSets,
                            reps: targetReps,
                        })}
                    </Badge>

                    <div className="space-y-2" key={`${current.slug}-sets`}>
                        {Array.from(
                            { length: rowCount },
                            (_, setIndex) => setIndex,
                        )
                            .filter(
                                (setIndex) =>
                                    !(hiddenSets[current.slug] ?? []).includes(
                                        setIndex,
                                    ),
                            )
                            .map((setIndex, position) => (
                                <PlayerSetRow
                                    key={`${current.slug}-${setIndex}-${session.id}`}
                                    position={position + 1}
                                    logged={
                                        logged.find(
                                            (set) => set.set_index === setIndex,
                                        ) ?? null
                                    }
                                    targetReps={targetReps}
                                    onSave={(weight, reps) =>
                                        saveSet(
                                            current.slug,
                                            setIndex,
                                            weight,
                                            reps,
                                        )
                                    }
                                    onRemove={() =>
                                        removeSet(current.slug, setIndex)
                                    }
                                />
                            ))}
                    </div>

                    {rowCount < 50 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="w-full"
                            onClick={() =>
                                setExtraSets((map) => ({
                                    ...map,
                                    [current.slug]:
                                        (map[current.slug] ?? 0) + 1,
                                }))
                            }
                        >
                            <Plus className="size-4" />
                            {t('shared_program.add_set')}
                        </Button>
                    )}
                </CardContent>
            </Card>

            <div className="mt-auto flex items-center gap-3 pt-4">
                <Button
                    variant="outline"
                    className="flex-1"
                    disabled={index === 0}
                    onClick={() => setIndex((i) => Math.max(0, i - 1))}
                >
                    <ChevronLeft className="size-4" />
                    {t('shared_program.previous')}
                </Button>
                <Button
                    className="flex-1 bg-lime-400 text-lime-950 hover:bg-lime-500"
                    onClick={() => (isLast ? finish() : setIndex((i) => i + 1))}
                >
                    {isLast
                        ? t('shared_program.finish')
                        : t('shared_program.next')}
                    {isLast ? (
                        <Check className="size-4" />
                    ) : (
                        <ChevronRight className="size-4" />
                    )}
                </Button>
            </div>
        </div>
    );
}
