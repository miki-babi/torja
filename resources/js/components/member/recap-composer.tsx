import { ImagePlus, Loader2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    exerciseName,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import { memberApiForm } from '@/components/member/member-api';
import { RecapSummaryBlock } from '@/components/member/recap-summary';
import type {
    Recap,
    RecapSummary,
    WorkoutSession,
    WorkoutSetEntry,
} from '@/components/member/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/lib/i18n';
import { store } from '@/routes/member/recaps';

const MAX_CAPTION = 280;

/**
 * Preview the exercise summary a recap post will show — mirrors
 * Recap::summary() on the server (best set per exercise by weight×reps).
 */
export function sessionToRecapSummary(
    session: WorkoutSession,
    exerciseSummaries: ExerciseSummaries,
    title: string | null,
): RecapSummary {
    const byExercise = new Map<string, WorkoutSetEntry[]>();
    for (const set of session.sets) {
        const list = byExercise.get(set.exercise_id) ?? [];
        list.push(set);
        byExercise.set(set.exercise_id, list);
    }

    const exercises = [...byExercise.entries()].map(([exerciseId, sets]) => {
        const best = [...sets].sort(
            (a, b) =>
                (b.weight_kg ?? 0) * 1000 +
                (b.reps ?? 0) -
                ((a.weight_kg ?? 0) * 1000 + (a.reps ?? 0)),
        )[0];

        return {
            name: exerciseName(exerciseSummaries, exerciseId),
            sets: sets.length,
            weight_kg: best?.weight_kg ?? null,
            reps: best?.reps ?? null,
        };
    });

    return {
        title,
        duration_minutes: null,
        exercise_count: exercises.length,
        set_count: session.sets.length,
        volume_kg: session.sets.reduce(
            (sum, set) => sum + (set.weight_kg ?? 0) * (set.reps ?? 0),
            0,
        ),
        exercises,
    };
}

/**
 * Share-a-recap dialog: optional photo + caption over a live preview of the
 * exercise summary card. Posts multipart to member.recaps.store.
 */
export function RecapComposer({
    open,
    onOpenChange,
    session,
    title,
    exerciseSummaries,
    onPosted,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    session: WorkoutSession;
    title: string | null;
    exerciseSummaries: ExerciseSummaries;
    onPosted: (recap: Recap) => void;
}) {
    const { t } = useTranslation();
    const [caption, setCaption] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        return () => {
            if (preview !== null) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    function pickFile(next: File | null) {
        if (preview !== null) {
            URL.revokeObjectURL(preview);
        }
        setFile(next);
        setPreview(next === null ? null : URL.createObjectURL(next));
    }

    async function submit() {
        if (busy || session.sets.length === 0) {
            return;
        }
        setBusy(true);
        try {
            const form = new FormData();
            form.set('workout_session_id', String(session.id));
            if (caption.trim() !== '') {
                form.set('caption', caption.trim());
            }
            if (file !== null) {
                form.set('photo', file);
            }

            const { recap } = await memberApiForm<{ recap: Recap }>(
                store().url,
                form,
            );
            toast.success(t('member.recap.published_toast'));
            onPosted(recap);
            onOpenChange(false);
            setCaption('');
            pickFile(null);
        } catch (error) {
            const message =
                error instanceof Error && error.message !== ''
                    ? error.message
                    : t('member.recap.publish_failed_toast');
            toast.error(message);
        } finally {
            setBusy(false);
        }
    }

    const summary = sessionToRecapSummary(session, exerciseSummaries, title);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-md">
                <DialogHeader className="shrink-0 px-6 pt-6">
                    <DialogTitle>
                        {t('member.recap.composer_title')}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        {t('member.recap.composer_title')}
                    </DialogDescription>
                </DialogHeader>

                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(e) => {
                        pickFile(e.target.files?.[0] ?? null);
                        e.target.value = '';
                    }}
                />

                <div className="mt-4 flex-1 space-y-4 overflow-y-auto px-6">
                    {preview !== null ? (
                        <div className="relative overflow-hidden rounded-2xl border border-[#2E2E2E]">
                            <img
                                src={preview}
                                alt=""
                                className="max-h-56 w-full object-cover"
                            />
                            <div className="absolute top-2 right-2 flex gap-1.5">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="h-7 bg-black/60 text-xs text-white backdrop-blur-md hover:bg-black/80"
                                    onClick={() => inputRef.current?.click()}
                                >
                                    {t('member.recap.photo_change')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="icon"
                                    className="size-7 bg-black/60 text-white backdrop-blur-md hover:bg-black/80"
                                    onClick={() => pickFile(null)}
                                    aria-label="Remove photo"
                                >
                                    <X className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-[#3a3a3a] bg-[#1F1F1F] py-6 text-xs font-semibold text-[#A1A1AA] transition-colors hover:border-[#AFFA01]/40 hover:text-[#AFFA01]"
                        >
                            <ImagePlus className="size-4" />
                            {t('member.recap.photo_add')}
                        </button>
                    )}

                    <div>
                        <textarea
                            value={caption}
                            onChange={(e) =>
                                setCaption(e.target.value.slice(0, MAX_CAPTION))
                            }
                            placeholder={t('member.recap.caption_placeholder')}
                            rows={2}
                            className="w-full resize-none rounded-xl border border-[#2E2E2E] bg-[#1F1F1F] px-3 py-2.5 text-sm text-[#F5F5F5] placeholder:text-[#666666] focus:border-[#AFFA01]/50 focus:outline-none"
                        />
                        <p className="mt-0.5 text-right text-[10px] text-[#666666]">
                            {caption.length}/{MAX_CAPTION}
                        </p>
                    </div>

                    <RecapSummaryBlock summary={summary} t={t} />
                </div>

                <div className="shrink-0 border-t border-[#2E2E2E] px-6 py-4">
                    <Button
                        type="button"
                        disabled={busy || session.sets.length === 0}
                        onClick={submit}
                        className="w-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black shadow-md hover:brightness-105"
                    >
                        {busy && <Loader2 className="size-4 animate-spin" />}
                        {t('member.recap.publish')}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
