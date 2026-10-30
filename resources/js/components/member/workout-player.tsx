import { usePage } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronUp,
    ListCollapse,
    Maximize2,
    PlayCircle,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    tracksWeight,
    type ExerciseSummary,
} from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import type { WorkoutSession } from '@/components/member/types';
import { WorkoutShareCard } from '@/components/member/workout-share-card';
import { cn } from '@/lib/utils';
import { destroy, upsert } from '@/routes/member/sessions/sets';

export const DEFAULT_SETS = 3;
export const DEFAULT_REPS = 10;

export type PlayerExercise = ExerciseSummary & {
    gif_url: string;
    sets: number | null;
    reps: number | null;
    instructions?: string[] | null;
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

function GlassyCircleButton({
    icon: Icon,
    onClick,
    ariaLabel,
    disabled = false,
}: {
    icon: React.ComponentType<{ className?: string }>;
    onClick: () => void;
    ariaLabel?: string;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={ariaLabel}
            className="flex size-9 items-center justify-center rounded-full border border-[#2E2E2E] bg-[#1F1F1F]/90 text-[#F5F5F5] shadow-md backdrop-blur-md transition-all hover:border-[#AFFA01]/40 hover:text-[#AFFA01] active:scale-90 disabled:pointer-events-none disabled:opacity-40"
        >
            <Icon className="size-4" />
        </button>
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
    const total = workout.exercises.length;
    const [exerciseIndex, setExerciseIndex] = useState(0);
    const [currentSetIndex, setCurrentSetIndex] = useState(0);
    const [weight, setWeight] = useState<number>(20);
    const [reps, setReps] = useState<number>(10);
    const [setJustCompleted, setSetJustCompleted] = useState(false);
    const [completed, setCompleted] = useState(false);
    const [durationMinutes, setDurationMinutes] = useState(0);
    const startedAt = useRef(Date.now());
    const { name: appName } = usePage<{ name: string }>().props;
    const [showHowTo, setShowHowTo] = useState(false);
    const [showFullscreen, setShowFullscreen] = useState(false);
    const [showSetsDrawer, setShowSetsDrawer] = useState(false);
    const [editingMetric, setEditingMetric] = useState<
        'weight' | 'reps' | null
    >(null);
    const [customValue, setCustomValue] = useState('');

    const current = workout.exercises[exerciseIndex] ?? null;

    // Logged sets for current exercise
    const loggedSets = current
        ? session.sets
              .filter((s) => s.exercise_id === current.slug)
              .sort((a, b) => a.set_index - b.set_index)
        : [];

    const targetSets = current?.sets ?? DEFAULT_SETS;
    const targetReps = current?.reps ?? DEFAULT_REPS;

    // Synchronize default weight and reps when exercise changes or when loggedSets updates
    useEffect(() => {
        if (!current) return;

        // If current set has already been logged, load its values
        const thisSet = loggedSets.find((s) => s.set_index === currentSetIndex);
        if (thisSet) {
            if (thisSet.weight_kg !== null) setWeight(thisSet.weight_kg);
            if (thisSet.reps !== null) setReps(thisSet.reps);
            return;
        }

        // Otherwise load from latest logged set for this exercise
        if (loggedSets.length > 0) {
            const last = loggedSets[loggedSets.length - 1];
            if (last.weight_kg !== null) setWeight(last.weight_kg);
            if (last.reps !== null) setReps(last.reps);
        } else {
            setWeight(20);
            setReps(targetReps);
        }
    }, [exerciseIndex, currentSetIndex]);

    if (!current) {
        return null;
    }

    const previousSet =
        loggedSets.length > 0 ? loggedSets[loggedSets.length - 1] : null;
    const unweighted = !tracksWeight(current.equipment);

    async function saveSet(
        slug: string,
        setIndex: number,
        weightKg: number | null,
        repsCount: number | null,
    ) {
        try {
            const { session: next } = await memberApi<{
                session: WorkoutSession;
            }>(upsert({ session: session.id }).url, 'PUT', {
                exercise_id: slug,
                set_index: setIndex,
                weight_kg: weightKg,
                reps: repsCount,
            });
            onSessionChange(next);
            return next;
        } catch {
            toast.error(t('shared_program.set_save_failed_toast'));
            return null;
        }
    }

    async function removeSet(setId: number) {
        try {
            const { session: next } = await memberApi<{
                session: WorkoutSession;
            }>(destroy({ session: session.id, set: setId }).url, 'DELETE');
            onSessionChange(next);
            toast.success('Set removed');
        } catch {
            toast.error(t('shared_program.set_save_failed_toast'));
        }
    }

    function triggerHaptic() {
        if (typeof window !== 'undefined' && 'vibrate' in navigator) {
            try {
                navigator.vibrate(25);
            } catch {
                // Ignore vibration failure
            }
        }
    }

    function finishWorkout() {
        if (session.sets.length === 0) {
            onExit();
            return;
        }
        setDurationMinutes(
            Math.max(1, Math.round((Date.now() - startedAt.current) / 60000)),
        );
        setCompleted(true);
    }

    async function handleCompleteSet() {
        if (setJustCompleted) return;

        triggerHaptic();
        setSetJustCompleted(true);

        // Commit current set to backend
        await saveSet(
            current.slug,
            currentSetIndex,
            unweighted ? null : weight,
            reps,
        );

        setTimeout(() => {
            setSetJustCompleted(false);

            if (currentSetIndex + 1 < targetSets) {
                // Next set in same exercise
                setCurrentSetIndex((prev) => prev + 1);
            } else if (exerciseIndex + 1 < total) {
                // Advance to next exercise
                setExerciseIndex((prev) => prev + 1);
                setCurrentSetIndex(0);
            } else {
                // Final set of final exercise!
                toast.success(
                    t('shared_program.workout_done') ||
                        'Workout completed! Great job! 🎉',
                );
                finishWorkout();
            }
        }, 450);
    }

    function handleSkipExercise() {
        triggerHaptic();
        if (exerciseIndex + 1 < total) {
            setExerciseIndex((prev) => prev + 1);
            setCurrentSetIndex(0);
        } else {
            toast.success(
                t('shared_program.workout_done') || 'Workout completed!',
            );
            finishWorkout();
        }
    }

    function handleOpenNumericEdit(metric: 'weight' | 'reps') {
        setEditingMetric(metric);
        setCustomValue(metric === 'weight' ? String(weight) : String(reps));
    }

    function handleApplyCustomValue() {
        const parsed = Number.parseFloat(customValue);
        if (!Number.isNaN(parsed) && parsed >= 0) {
            if (editingMetric === 'weight') {
                setWeight(Math.min(500, Math.round(parsed * 10) / 10));
            } else if (editingMetric === 'reps') {
                setReps(Math.min(150, Math.max(1, Math.round(parsed))));
            }
        }
        setEditingMetric(null);
    }

    if (completed) {
        return (
            <div className="fixed inset-0 z-50 text-[#F5F5F5] select-none">
                <WorkoutShareCard
                    appName={appName}
                    workoutTitle={workout.title}
                    durationMinutes={durationMinutes}
                    session={session}
                    labels={{
                        duration: t('workout_share.duration'),
                        exercises: t('workout_share.exercises'),
                        sets: t('workout_share.sets'),
                        volume: t('workout_share.volume'),
                    }}
                />
                <button
                    type="button"
                    onClick={onExit}
                    aria-label="Close"
                    className="absolute top-[calc(1.5rem+var(--safe-top))] right-6 z-10 flex size-10 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur-md transition-all hover:bg-black/70 active:scale-90"
                >
                    <X className="size-5" />
                </button>
            </div>
        );
    }

    return (
        <div className="flex min-h-[90vh] flex-col justify-between pb-28 text-[#F5F5F5] select-none">
            {/* Top Bar Header */}
            <div className="relative flex items-center justify-center border-b border-[#2E2E2E]/60 pb-3">
                <div className="text-center">
                    <p className="text-xs font-semibold tracking-wider text-[#AFFA01] uppercase">
                        {workout.title}
                    </p>
                    <h1 className="text-sm font-bold text-[#F5F5F5]">
                        Exercise {exerciseIndex + 1} of {total}
                    </h1>
                </div>

                <button
                    type="button"
                    onClick={finishWorkout}
                    className="absolute right-0 text-xs font-bold tracking-wider text-[#AFFA01] uppercase hover:underline"
                >
                    Finish
                </button>
            </div>

            {/* Segmented Exercise Progress Track */}
            <div className="mt-3 flex gap-1.5 px-0.5">
                {workout.exercises.map((ex, i) => {
                    const exLogged = session.sets.some(
                        (s) => s.exercise_id === ex.slug,
                    );
                    const isCurrent = i === exerciseIndex;
                    return (
                        <button
                            key={ex.slug}
                            type="button"
                            onClick={() => {
                                setExerciseIndex(i);
                                setCurrentSetIndex(0);
                            }}
                            className={cn(
                                'h-1.5 flex-1 rounded-full transition-all',
                                isCurrent
                                    ? 'bg-[#AFFA01] shadow-[0_0_10px_rgba(175,250,1,0.5)]'
                                    : exLogged
                                      ? 'bg-[#AFFA01]/60'
                                      : 'bg-[#2E2E2E]',
                            )}
                            aria-label={`Jump to ${ex.name}`}
                        />
                    );
                })}
            </div>

            {/* Main Interactive Workout Arena */}
            <div className="my-auto flex flex-col items-center py-4">
                {/* Exercise Visual Container */}
                <div className="group relative w-full max-w-[280px] overflow-hidden rounded-[22px] border border-[#E4E4E7] bg-white shadow-lg">
                    <img
                        key={current.slug}
                        src={current.gif_url || current.image_url}
                        alt={current.name}
                        onClick={() => setShowHowTo(true)}
                        className="aspect-[4/3] w-full cursor-pointer object-cover transition-transform duration-300 group-hover:scale-105"
                    />

                    {/* Fullscreen Button */}
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            setShowFullscreen(true);
                        }}
                        className="absolute right-2.5 bottom-2.5 flex size-8 items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-md hover:bg-black/80 active:scale-95"
                        aria-label="Fullscreen view"
                    >
                        <Maximize2 className="size-4" />
                    </button>
                </div>

                {/* "How to do this" Button with Accent Icon */}
                <button
                    type="button"
                    onClick={() => setShowHowTo(true)}
                    className="mt-3.5 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold text-[#AFFA01] transition-colors hover:bg-[#AFFA01]/10 active:scale-95"
                >
                    <PlayCircle className="size-4 text-[#AFFA01]" />
                    How to do this
                </button>

                {/* Exercise Title & Set Indicator */}
                <div
                    className={cn(
                        'mt-3 text-center transition-transform duration-250',
                        setJustCompleted && 'animate-pg-pulse scale-105',
                    )}
                >
                    <h2
                        onClick={() => setShowHowTo(true)}
                        className="cursor-pointer text-2xl font-bold tracking-tight text-[#F5F5F5] transition-colors hover:text-[#AFFA01]"
                    >
                        {current.name}
                    </h2>

                    <p className="mt-1 text-sm font-semibold tracking-wide text-[#8E8E93]">
                        Set {currentSetIndex + 1} of {targetSets}
                    </p>

                    <p className="mt-1 text-xs text-[#666666]">
                        {previousSet
                            ? previousSet.weight_kg
                                ? `Previous: ${previousSet.weight_kg} kg × ${previousSet.reps ?? targetReps} reps`
                                : `Previous: ${previousSet.reps ?? targetReps} reps`
                            : `Target: ${targetReps} reps · ${current.body_part || 'Full body'}`}
                    </p>
                </div>

                {/* PrimeGym Metric Steppers Row */}
                <div className="mt-6 flex w-full max-w-sm items-center justify-between gap-3 px-2">
                    {/* Weight Metric Stepper */}
                    {!unweighted && (
                        <>
                            <div className="flex-1">
                                <span className="block text-center text-[11px] font-bold tracking-wider text-[#8E8E93] uppercase">
                                    Weight (kg)
                                </span>
                                <div className="mt-2 flex items-center gap-1.5">
                                    {/* Up/Down Chevrons on Left */}
                                    <div className="flex flex-col gap-1.5">
                                        <GlassyCircleButton
                                            icon={ChevronUp}
                                            onClick={() => {
                                                triggerHaptic();
                                                setWeight((w) =>
                                                    Math.min(
                                                        500,
                                                        Math.round(
                                                            (w + 2.5) * 10,
                                                        ) / 10,
                                                    ),
                                                );
                                            }}
                                            ariaLabel="Increase weight"
                                        />
                                        <GlassyCircleButton
                                            icon={ChevronDown}
                                            onClick={() => {
                                                triggerHaptic();
                                                setWeight((w) =>
                                                    Math.max(
                                                        0,
                                                        Math.round(
                                                            (w - 2.5) * 10,
                                                        ) / 10,
                                                    ),
                                                );
                                            }}
                                            ariaLabel="Decrease weight"
                                        />
                                    </div>

                                    {/* Large Tactile Display Box */}
                                    <button
                                        type="button"
                                        onClick={() =>
                                            handleOpenNumericEdit('weight')
                                        }
                                        className="flex flex-1 flex-col items-center justify-center rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] py-5 shadow-sm transition-all hover:border-[#AFFA01]/40 active:scale-[0.98]"
                                    >
                                        <span className="font-mono text-2xl font-bold tracking-tight text-[#F5F5F5]">
                                            {weight % 1 === 0
                                                ? weight
                                                : weight.toFixed(1)}
                                        </span>
                                        <span className="text-[10px] font-semibold text-[#8E8E93] uppercase">
                                            kg
                                        </span>
                                    </button>
                                </div>
                            </div>

                            {/* Central Multiplication Sign */}
                            <div className="pt-6 text-2xl font-bold text-[#555555]">
                                ×
                            </div>
                        </>
                    )}

                    {/* Reps Metric Stepper */}
                    <div className="flex-1">
                        <span className="block text-center text-[11px] font-bold tracking-wider text-[#8E8E93] uppercase">
                            Reps
                        </span>
                        <div className="mt-2 flex items-center gap-1.5">
                            {/* Large Tactile Display Box */}
                            <button
                                type="button"
                                onClick={() => handleOpenNumericEdit('reps')}
                                className="flex flex-1 flex-col items-center justify-center rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] py-5 shadow-sm transition-all hover:border-[#AFFA01]/40 active:scale-[0.98]"
                            >
                                <span className="font-mono text-2xl font-bold tracking-tight text-[#F5F5F5]">
                                    {reps}
                                </span>
                                <span className="text-[10px] font-semibold text-[#8E8E93] uppercase">
                                    reps
                                </span>
                            </button>

                            {/* Up/Down Chevrons on Right */}
                            <div className="flex flex-col gap-1.5">
                                <GlassyCircleButton
                                    icon={ChevronUp}
                                    onClick={() => {
                                        triggerHaptic();
                                        setReps((r) => Math.min(100, r + 1));
                                    }}
                                    ariaLabel="Increase reps"
                                />
                                <GlassyCircleButton
                                    icon={ChevronDown}
                                    onClick={() => {
                                        triggerHaptic();
                                        setReps((r) => Math.max(1, r - 1));
                                    }}
                                    ariaLabel="Decrease reps"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Logged Sets Mini Drawer Pill */}
                {loggedSets.length > 0 && (
                    <button
                        type="button"
                        onClick={() => setShowSetsDrawer(true)}
                        className="mt-5 inline-flex items-center gap-1.5 rounded-full border border-[#2E2E2E] bg-[#1F1F1F]/60 px-3 py-1 text-xs text-[#8E8E93] transition-colors hover:border-[#AFFA01]/30 hover:text-[#F5F5F5]"
                    >
                        <ListCollapse className="size-3.5 text-[#AFFA01]" />
                        <span>
                            {loggedSets.length} set
                            {loggedSets.length > 1 ? 's' : ''} logged for this
                            exercise
                        </span>
                    </button>
                )}
            </div>

            {/* Bottom Actions Bar — pinned to the viewport */}
            <div className="fixed inset-x-0 bottom-0 z-40">
                <div className="mx-auto flex w-full max-w-md items-center gap-3 bg-gradient-to-t from-black via-black/95 to-transparent px-4 pt-6 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    {/* Skip Exercise (Secondary Variant) */}
                    <button
                        type="button"
                        disabled={setJustCompleted}
                        onClick={handleSkipExercise}
                        className="flex h-14 flex-1 items-center justify-center rounded-2xl border border-[#AFFA01]/30 bg-[#AFFA01]/10 px-4 text-sm font-bold text-[#F5F5F5] transition-all hover:bg-[#AFFA01]/20 active:scale-[0.98] disabled:opacity-40"
                    >
                        Skip
                    </button>

                    {/* Complete Set (Primary Fit Green Gradient Variant) */}
                    <button
                        type="button"
                        disabled={setJustCompleted}
                        onClick={handleCompleteSet}
                        className={cn(
                            'flex h-14 flex-[2] items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-6 text-base font-bold text-black shadow-[0_8px_24px_rgba(175,250,1,0.28)] transition-all active:scale-[0.98] disabled:opacity-80',
                            setJustCompleted && 'scale-[1.02] bg-[#AFFA01]',
                        )}
                    >
                        {setJustCompleted ? (
                            <>
                                <Check className="size-5 stroke-[3]" />
                                <span>Nice!</span>
                            </>
                        ) : (
                            <span>Complete Set</span>
                        )}
                    </button>
                </div>
            </div>

            {/* Numeric Edit Modal Dialog */}
            {editingMetric && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-xs rounded-[24px] border border-[#2E2E2E] bg-[#1F1F1F] p-6 shadow-2xl">
                        <h3 className="text-center text-base font-bold text-[#F5F5F5]">
                            Edit{' '}
                            {editingMetric === 'weight'
                                ? 'Weight (kg)'
                                : 'Reps'}
                        </h3>
                        <div className="mt-4">
                            <input
                                type="number"
                                step={editingMetric === 'weight' ? '0.5' : '1'}
                                min="0"
                                autoFocus
                                value={customValue}
                                onChange={(e) => setCustomValue(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter')
                                        handleApplyCustomValue();
                                }}
                                className="w-full rounded-xl border border-[#2E2E2E] bg-[#121212] px-4 py-3 text-center font-mono text-2xl font-bold text-[#F5F5F5] outline-none focus:border-[#AFFA01]"
                            />
                        </div>
                        <div className="mt-5 flex gap-2">
                            <button
                                type="button"
                                onClick={() => setEditingMetric(null)}
                                className="flex-1 rounded-xl border border-[#2E2E2E] bg-[#242424] py-2.5 text-xs font-semibold text-[#A1A1AA] hover:text-[#F5F5F5]"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleApplyCustomValue}
                                className="flex-1 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] py-2.5 text-xs font-bold text-black shadow-md"
                            >
                                Done
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* "How to do this" Modal / Slide-up Sheet */}
            {showHowTo && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/80 backdrop-blur-sm">
                    <div className="max-h-[85vh] w-full max-w-md overflow-y-auto rounded-t-[28px] border-t border-[#2E2E2E] bg-[#1F1F1F] p-6 shadow-2xl">
                        {/* Drag Handle */}
                        <div className="mx-auto h-1 w-10 rounded-full bg-[#3E3E3E]" />

                        <div className="mt-4 flex items-center justify-between">
                            <h3 className="text-xl font-bold text-[#F5F5F5]">
                                {current.name}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setShowHowTo(false)}
                                className="flex size-8 items-center justify-center rounded-full bg-[#2A2A2A] text-[#A1A1AA] hover:text-[#F5F5F5]"
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        {/* Media Visual */}
                        <div className="mt-4 overflow-hidden rounded-2xl border border-[#E4E4E7] bg-white">
                            <img
                                src={current.gif_url || current.image_url}
                                alt={current.name}
                                className="aspect-video w-full object-contain"
                            />
                        </div>

                        {/* Target Chips */}
                        <div className="mt-4 flex flex-wrap gap-2">
                            {current.body_part && (
                                <span className="rounded-full border border-[#AFFA01]/30 bg-[#AFFA01]/10 px-3 py-1 text-xs font-semibold text-[#AFFA01]">
                                    {current.body_part}
                                </span>
                            )}
                            {current.equipment && (
                                <span className="rounded-full border border-[#2E2E2E] bg-[#2A2A2A] px-3 py-1 text-xs font-medium text-[#F5F5F5]">
                                    {current.equipment}
                                </span>
                            )}
                            {current.target && (
                                <span className="rounded-full border border-[#2E2E2E] bg-[#2A2A2A] px-3 py-1 text-xs font-medium text-[#A1A1AA]">
                                    Target: {current.target}
                                </span>
                            )}
                        </div>

                        {/* Instructions */}
                        <div className="mt-5">
                            <h4 className="text-sm font-bold tracking-wider text-[#AFFA01] uppercase">
                                Instructions
                            </h4>
                            <p className="mt-2 text-sm leading-relaxed text-[#D4D4D8]">
                                {current.detail ||
                                    'Perform with controlled tempo. Focus on muscle contraction and keep your core braced throughout the movement.'}
                            </p>
                        </div>

                        {/* Form Tips */}
                        <div className="mt-5 rounded-2xl border border-[#2E2E2E] bg-[#141414] p-4">
                            <h4 className="text-xs font-bold tracking-wider text-[#AFFA01] uppercase">
                                Form Tips
                            </h4>
                            <ul className="mt-2 space-y-1.5 text-xs text-[#A1A1AA]">
                                <li>
                                    • Keep a neutral spine and inhale on
                                    eccentric, exhale on concentric.
                                </li>
                                <li>
                                    • Avoid using momentum to lift the weight.
                                </li>
                                <li>
                                    • Control the descent for maximum
                                    hypertrophy benefit.
                                </li>
                            </ul>
                        </div>

                        <button
                            type="button"
                            onClick={() => setShowHowTo(false)}
                            className="mt-6 w-full rounded-2xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] py-3.5 text-sm font-bold text-black shadow-lg"
                        >
                            Got It
                        </button>
                    </div>
                </div>
            )}

            {/* Fullscreen Video/GIF Viewer */}
            {showFullscreen && (
                <div
                    onClick={() => setShowFullscreen(false)}
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/95 p-4 backdrop-blur-md"
                >
                    <button
                        type="button"
                        onClick={() => setShowFullscreen(false)}
                        className="absolute top-6 right-6 flex size-10 items-center justify-center rounded-full bg-[#1F1F1F] text-white"
                        aria-label="Close fullscreen"
                    >
                        <X className="size-5" />
                    </button>
                    <img
                        src={current.gif_url || current.image_url}
                        alt={current.name}
                        className="max-h-[85vh] max-w-full rounded-2xl bg-white object-contain shadow-2xl"
                    />
                </div>
            )}

            {/* Logged Sets Drawer */}
            {showSetsDrawer && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/80 backdrop-blur-sm">
                    <div className="max-h-[70vh] w-full max-w-md overflow-y-auto rounded-t-[28px] border-t border-[#2E2E2E] bg-[#1F1F1F] p-6 shadow-2xl">
                        <div className="mx-auto h-1 w-10 rounded-full bg-[#3E3E3E]" />

                        <div className="mt-4 flex items-center justify-between">
                            <h3 className="text-base font-bold text-[#F5F5F5]">
                                Logged Sets — {current.name}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setShowSetsDrawer(false)}
                                className="flex size-8 items-center justify-center rounded-full bg-[#2A2A2A] text-[#A1A1AA]"
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="mt-4 divide-y divide-[#2E2E2E]">
                            {loggedSets.map((s, idx) => (
                                <div
                                    key={s.id}
                                    className="flex items-center justify-between py-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-[#AFFA01] text-xs font-bold text-black">
                                            {idx + 1}
                                        </span>
                                        <span className="font-mono text-sm font-semibold text-[#F5F5F5]">
                                            {s.weight_kg
                                                ? `${s.weight_kg} kg × ${s.reps ?? targetReps} reps`
                                                : `${s.reps ?? targetReps} reps`}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => removeSet(s.id)}
                                        className="p-1 text-[#8E8E93] transition-colors hover:text-[#FF6B6B]"
                                        aria-label={`Remove set ${idx + 1}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            ))}
                        </div>

                        <button
                            type="button"
                            onClick={() => setShowSetsDrawer(false)}
                            className="mt-6 w-full rounded-2xl bg-[#2A2A2A] py-3 text-xs font-bold tracking-wider text-[#F5F5F5] uppercase"
                        >
                            Close
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
