import { Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    exerciseName,
    type BodyPart,
    type ExerciseSummaries,
    type ExerciseSummary,
} from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import type { WorkoutSession } from '@/components/member/types';
import { tracksWeight } from '@/components/member/exercise-data';
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
import { useTranslation } from '@/lib/i18n';
import { backfill } from '@/routes/member/sessions';
import { ExerciseSearchDialog } from '@/components/member/exercise-search-dialog';

type DraftSet = { weight: string; reps: string };
type DraftExercise = { exercise: ExerciseSummary; sets: DraftSet[] };

function yesterdayKey(): string {
    const day = new Date();
    day.setDate(day.getDate() - 1);
    return day.toISOString().slice(0, 10);
}

/**
 * Backfill a whole past workout: pick a date, add exercises, type the
 * sets. One POST to member.sessions.backfill merges everything into the
 * day's free session.
 */
export function LogPastWorkoutDialog({
    open,
    onOpenChange,
    exerciseSummaries,
    sessions = [],
    bodyParts = [],
    equipment = [],
    onLogged,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    exerciseSummaries: ExerciseSummaries;
    sessions?: WorkoutSession[];
    bodyParts?: BodyPart[];
    equipment?: string[];
    onLogged: (session: WorkoutSession, added: ExerciseSummary[]) => void;
}) {
    const { t } = useTranslation();
    const [date, setDate] = useState(yesterdayKey);
    const [rows, setRows] = useState<DraftExercise[]>([]);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            setDate(yesterdayKey());
            setRows([]);
        }
    }, [open]);

    const valid =
        date !== '' &&
        rows.every((row) =>
            row.sets.every((s) => s.reps.trim() !== '' && Number(s.reps) >= 1),
        ) &&
        rows.some((row) => row.sets.length > 0);

    async function save() {
        if (!valid || saving) {
            return;
        }
        setSaving(true);
        try {
            const payload = {
                date,
                sets: rows.flatMap((row) =>
                    row.sets.map((s) => ({
                        exercise_id: row.exercise.slug,
                        weight_kg:
                            s.weight.trim() === ''
                                ? null
                                : Number.parseFloat(s.weight),
                        reps: Number(s.reps),
                    })),
                ),
            };
            const { session } = await memberApi<{
                session: WorkoutSession;
            }>(backfill().url, 'POST', payload);
            onLogged(
                session,
                rows.map((row) => row.exercise),
            );
            onOpenChange(false);
        } catch {
            toast.error('Could not save workout');
        } finally {
            setSaving(false);
        }
    }

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {t('member.progress.log_past')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('member.progress.log_past_hint')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="backfill-date">
                            {t('member.progress.workout_date')}
                        </Label>
                        <Input
                            id="backfill-date"
                            type="date"
                            value={date}
                            max={new Date().toISOString().slice(0, 10)}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </div>

                    <div className="space-y-3">
                        {rows.map((row, rowIndex) => {
                            const weighted = tracksWeight(
                                row.exercise.equipment,
                            );
                            return (
                                <div
                                    key={row.exercise.slug}
                                    className="rounded-xl border border-[#2E2E2E] p-3"
                                >
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                                            {exerciseName(
                                                exerciseSummaries,
                                                row.exercise.slug,
                                            )}
                                        </p>
                                        <button
                                            type="button"
                                            aria-label={`Remove ${row.exercise.name}`}
                                            onClick={() =>
                                                setRows((list) =>
                                                    list.filter(
                                                        (_, i) =>
                                                            i !== rowIndex,
                                                    ),
                                                )
                                            }
                                            className="shrink-0 text-[#8E8E93] hover:text-[#FF6B6B]"
                                        >
                                            <X className="size-4" />
                                        </button>
                                    </div>
                                    <div className="space-y-1.5">
                                        {row.sets.map((s, setIndex) => (
                                            <div
                                                key={setIndex}
                                                className="flex items-center gap-2"
                                            >
                                                <span className="w-6 text-center text-xs text-muted-foreground">
                                                    {setIndex + 1}
                                                </span>
                                                {weighted && (
                                                    <>
                                                        <Input
                                                            type="number"
                                                            inputMode="decimal"
                                                            min="0"
                                                            step="any"
                                                            value={s.weight}
                                                            placeholder="kg"
                                                            onChange={(e) =>
                                                                setRows(
                                                                    (list) =>
                                                                        list.map(
                                                                            (
                                                                                r,
                                                                                i,
                                                                            ) =>
                                                                                i ===
                                                                                rowIndex
                                                                                    ? {
                                                                                          ...r,
                                                                                          sets: r.sets.map(
                                                                                              (
                                                                                                  x,
                                                                                                  j,
                                                                                              ) =>
                                                                                                  j ===
                                                                                                  setIndex
                                                                                                      ? {
                                                                                                            ...x,
                                                                                                            weight: e
                                                                                                                .target
                                                                                                                .value,
                                                                                                        }
                                                                                                      : x,
                                                                                          ),
                                                                                      }
                                                                                    : r,
                                                                        ),
                                                                )
                                                            }
                                                            className="h-8 w-20"
                                                        />
                                                        <span className="text-xs text-muted-foreground">
                                                            ×
                                                        </span>
                                                    </>
                                                )}
                                                <Input
                                                    type="number"
                                                    inputMode="numeric"
                                                    min="1"
                                                    value={s.reps}
                                                    placeholder="reps"
                                                    onChange={(e) =>
                                                        setRows((list) =>
                                                            list.map((r, i) =>
                                                                i === rowIndex
                                                                    ? {
                                                                          ...r,
                                                                          sets: r.sets.map(
                                                                              (
                                                                                  x,
                                                                                  j,
                                                                              ) =>
                                                                                  j ===
                                                                                  setIndex
                                                                                      ? {
                                                                                            ...x,
                                                                                            reps: e
                                                                                                .target
                                                                                                .value,
                                                                                        }
                                                                                      : x,
                                                                          ),
                                                                      }
                                                                    : r,
                                                            ),
                                                        )
                                                    }
                                                    className="h-8 w-20"
                                                />
                                                <button
                                                    type="button"
                                                    aria-label={`Remove set ${setIndex + 1}`}
                                                    onClick={() =>
                                                        setRows((list) =>
                                                            list.map((r, i) =>
                                                                i === rowIndex
                                                                    ? {
                                                                          ...r,
                                                                          sets: r.sets.filter(
                                                                              (
                                                                                  _,
                                                                                  j,
                                                                              ) =>
                                                                                  j !==
                                                                                  setIndex,
                                                                          ),
                                                                      }
                                                                    : r,
                                                            ),
                                                        )
                                                    }
                                                    className="shrink-0 text-[#8E8E93] hover:text-[#FF6B6B]"
                                                >
                                                    <X className="size-4" />
                                                </button>
                                            </div>
                                        ))}
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setRows((list) =>
                                                    list.map((r, i) =>
                                                        i === rowIndex
                                                            ? {
                                                                  ...r,
                                                                  sets: [
                                                                      ...r.sets,
                                                                      {
                                                                          weight: '',
                                                                          reps: '',
                                                                      },
                                                                  ],
                                                              }
                                                            : r,
                                                    ),
                                                )
                                            }
                                            className="mt-1 flex items-center gap-1 text-xs font-semibold text-[#AFFA01]"
                                        >
                                            <Plus className="size-3.5" />
                                            {t('member.progress.add_set')}
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        className="w-full"
                        onClick={() => setPickerOpen(true)}
                    >
                        <Plus className="size-4" />
                        {t('member.progress.add_exercise')}
                    </Button>

                    <div className="flex justify-end">
                        <Button
                            disabled={!valid || saving}
                            onClick={save}
                            className="bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black hover:brightness-105"
                        >
                            {t('member.progress.save_workout')}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <ExerciseSearchDialog
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                sessions={sessions}
                exerciseSummaries={exerciseSummaries}
                bodyParts={bodyParts}
                equipment={equipment}
                onPick={(exercise) =>
                    setRows((list) =>
                        list.some((r) => r.exercise.slug === exercise.slug)
                            ? list
                            : [
                                  ...list,
                                  {
                                      exercise,
                                      sets: [{ weight: '', reps: '' }],
                                  },
                              ],
                    )
                }
            />
        </>
    );
}
