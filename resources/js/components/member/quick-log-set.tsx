import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { tracksWeight } from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import type {
    WorkoutSession,
    WorkoutSetEntry,
} from '@/components/member/types';
import {
    lastSessionWithExercise,
    setsForExercise,
    setSummary,
} from '@/components/member/workout-progress';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { logSet } from '@/routes/member/sessions';
import { destroy } from '@/routes/member/sessions/sets';

function parseOptional(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }
    const parsed = Number.parseFloat(value);
    return Number.isNaN(parsed) ? null : parsed;
}

/**
 * Quick "log a set" dialog: appends a set for one exercise to today's free
 * session (created on demand server-side). Stays open between saves so
 * several sets can be logged back-to-back.
 */
export function QuickLogSet({
    slug,
    name,
    equipment,
    sessions,
    open,
    onOpenChange,
    onLogged,
}: {
    slug: string;
    name: string;
    equipment: string;
    sessions: WorkoutSession[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onLogged: (session: WorkoutSession) => void;
}) {
    const unweighted = !tracksWeight(equipment);
    const [weight, setWeight] = useState('');
    const [reps, setReps] = useState('');
    const [loggedSets, setLoggedSets] = useState<WorkoutSetEntry[]>([]);
    const [busy, setBusy] = useState(false);
    const [removingId, setRemovingId] = useState<number | null>(null);
    const repsRef = useRef<HTMLInputElement>(null);

    const last = lastSessionWithExercise(sessions, slug);
    const lastSets = last === null ? [] : setsForExercise(last, slug);
    const lastSet = lastSets[lastSets.length - 1];
    const lastText = setSummary(lastSets);

    // Prefill from the previous session's last set each time the dialog opens.
    useEffect(() => {
        if (open) {
            setWeight(
                lastSet?.weight_kg == null ? '' : String(lastSet.weight_kg),
            );
            setReps(lastSet?.reps == null ? '' : String(lastSet.reps));
            const today = new Date().toISOString().slice(0, 10);
            setLoggedSets(
                sessions
                    .filter((s) => s.date === today)
                    .flatMap((s) => s.sets)
                    .filter((s) => s.exercise_id === slug)
                    .sort((a, b) => a.set_index - b.set_index),
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, slug]);

    async function save() {
        const repsValue = parseOptional(reps);
        if (busy || repsValue === null || repsValue < 1) {
            return;
        }
        setBusy(true);
        try {
            const { session } = await memberApi<{
                session: WorkoutSession;
            }>(logSet().url, 'POST', {
                exercise_id: slug,
                weight_kg: parseOptional(weight),
                reps: repsValue,
            });
            setLoggedSets((list) => {
                const merged = new Map(list.map((s) => [s.id, s]));
                for (const s of setsForExercise(session, slug)) {
                    merged.set(s.id, s);
                }
                return [...merged.values()].sort(
                    (a, b) => a.set_index - b.set_index,
                );
            });
            onLogged(session);
            // Prompt for the next set: clear reps and refocus the field.
            setReps('');
            repsRef.current?.focus();
        } catch {
            toast.error('Could not log set');
        } finally {
            setBusy(false);
        }
    }

    async function removeSet(entry: WorkoutSetEntry) {
        if (removingId !== null) {
            return;
        }
        setRemovingId(entry.id);
        try {
            const { session } = await memberApi<{
                session: WorkoutSession;
            }>(
                destroy({
                    session: entry.workout_session_id,
                    set: entry.id,
                }).url,
                'DELETE',
            );
            setLoggedSets((list) => list.filter((s) => s.id !== entry.id));
            onLogged(session);
        } catch {
            toast.error('Could not remove set');
        } finally {
            setRemovingId(null);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="capitalize">
                        Log a set — {name}
                    </DialogTitle>
                    <DialogDescription>
                        {loggedSets.length > 0
                            ? `${loggedSets.length} set${loggedSets.length === 1 ? '' : 's'} logged today`
                            : lastText !== ''
                              ? `Last time: ${lastText}`
                              : unweighted
                                ? 'No weight needed — just enter your reps.'
                                : 'Saved to today’s free session.'}
                    </DialogDescription>
                </DialogHeader>

                <form
                    className="grid gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void save();
                    }}
                >
                    {loggedSets.length > 0 && (
                        <div className="divide-y divide-[#2E2E2E] rounded-xl border border-[#2E2E2E] bg-[#1F1F1F]">
                            {loggedSets.map((s, i) => (
                                <div
                                    key={s.id}
                                    className="flex items-center justify-between gap-2 px-3.5 py-2 text-sm"
                                >
                                    <span className="text-xs text-[#8E8E93]">
                                        Set {i + 1}
                                    </span>
                                    <span className="min-w-0 flex-1 text-right font-semibold text-[#F5F5F5]">
                                        {setSummary([s])}
                                    </span>
                                    <button
                                        type="button"
                                        disabled={removingId === s.id}
                                        onClick={() => removeSet(s)}
                                        aria-label={`Remove set ${i + 1}`}
                                        className="shrink-0 text-[#8E8E93] transition-colors hover:text-[#FF6B6B] disabled:opacity-40"
                                    >
                                        <X className="size-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}

                    <div
                        className={
                            unweighted ? 'grid gap-3' : 'grid grid-cols-2 gap-3'
                        }
                    >
                        {!unweighted && (
                            <div className="grid gap-2">
                                <Label htmlFor="quick-log-weight">
                                    Weight (kg, optional)
                                </Label>
                                <Input
                                    id="quick-log-weight"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="any"
                                    value={weight}
                                    onChange={(e) => setWeight(e.target.value)}
                                    placeholder="Bodyweight"
                                    autoFocus
                                />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="quick-log-reps">Reps</Label>
                            <Input
                                id="quick-log-reps"
                                ref={repsRef}
                                type="number"
                                inputMode="numeric"
                                min="1"
                                value={reps}
                                onChange={(e) => setReps(e.target.value)}
                                placeholder="10"
                                autoFocus={unweighted}
                            />
                        </div>
                    </div>

                    <div className="flex justify-between">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            Done
                        </Button>
                        <Button
                            type="submit"
                            disabled={busy || reps.trim() === ''}
                            className="bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black shadow-md hover:brightness-105"
                        >
                            {loggedSets.length > 0
                                ? 'Log another set'
                                : 'Log set'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
