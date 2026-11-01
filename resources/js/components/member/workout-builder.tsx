import { Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Play } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { BodyPartPicker } from '@/components/member/body-part-picker';
import { EquipmentPicker } from '@/components/member/equipment-picker';
import {
    exerciseDetail,
    exerciseName,
    type BodyPart,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import type { ExercisePlaylist } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { show as exerciseShow } from '@/routes/member/exercises';
import { play } from '@/routes/member/playlists';
import { build } from '@/routes/member/workouts';

type Step = 'start' | 'muscle' | 'equipment' | 'exercises';

const STEP_INDEX: Record<Step, number> = {
    start: 1,
    muscle: 2,
    equipment: 3,
    exercises: 4,
};

type BuiltWorkout = {
    playlist: ExercisePlaylist;
    summaries: ExerciseSummaries;
};

type StoredState = {
    step: Step;
    muscles: string[];
    equipmentSel: string[];
    built: BuiltWorkout | null;
};

const STORAGE_KEY = 'workout-builder:v1';

/**
 * Wizard state lives in sessionStorage so navigating to an exercise's
 * player page and coming back restores the same result list instead of
 * resetting the wizard.
 */
function readStored(): StoredState | null {
    try {
        if (typeof sessionStorage === 'undefined') {
            return null;
        }

        const raw = sessionStorage.getItem(STORAGE_KEY);
        return raw === null ? null : (JSON.parse(raw) as StoredState);
    } catch {
        return null;
    }
}

/**
 * Four-step workout builder: start → pick target muscles → pick available
 * equipment → review the generated exercise list → start. Each step fills
 * the page like the exercise page's picker sections. Empty selections mean
 * "full body" / "any equipment". Sessions already logged today surface as
 * Continue rows on the start step.
 */
export function WorkoutBuilder({
    bodyParts,
    equipment,
}: {
    bodyParts: BodyPart[];
    equipment: string[];
}) {
    const { t } = useTranslation();
    const [stored] = useState<StoredState | null>(readStored);
    const [step, setStep] = useState<Step>(
        stored?.step === 'exercises' && stored.built === null
            ? 'start'
            : (stored?.step ?? 'start'),
    );
    const [muscles, setMuscles] = useState<string[]>(stored?.muscles ?? []);
    const [equipmentSel, setEquipmentSel] = useState<string[]>(
        stored?.equipmentSel ?? [],
    );
    const [built, setBuilt] = useState<BuiltWorkout | null>(
        stored?.built ?? null,
    );
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        try {
            sessionStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ step, muscles, equipmentSel, built }),
            );
        } catch {
            // Storage unavailable — the wizard just won't restore.
        }
    }, [step, muscles, equipmentSel, built]);

    async function preview() {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const data = await memberApi<{
                playlist: ExercisePlaylist;
                exerciseSummaries: ExerciseSummaries;
            }>(build().url, 'POST', {
                muscles,
                equipment: equipmentSel,
            });
            setBuilt({
                playlist: data.playlist,
                summaries: data.exerciseSummaries,
            });
            setStep('exercises');
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Could not build workout',
            );
        } finally {
            setBusy(false);
        }
    }

    function start() {
        if (built === null) {
            return;
        }

        router.visit(
            `${play(built.playlist.id).url}?back=${encodeURIComponent('/today')}`,
        );
    }

    const stepLabel = t('member.builder.step', {
        current: STEP_INDEX[step],
        total: 4,
    });

    return (
        <div className="space-y-5">
            <p className="text-[11px] font-bold tracking-wider text-[#AFFA01] uppercase">
                {stepLabel}
            </p>

            {step === 'start' && (
                <div className="space-y-4">
                    <div>
                        <h2 className="text-lg font-bold text-[#F5F5F5]">
                            {t('member.builder.title')}
                        </h2>
                        <p className="mt-1 text-sm text-[#8E8E93]">
                            {t('member.builder.subtitle')}
                        </p>
                    </div>
                    <Button
                        className="h-12 w-full justify-center rounded-2xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black hover:brightness-105"
                        onClick={() => setStep('muscle')}
                    >
                        <Play className="size-4 fill-current" />
                        {t('member.home.start_workout')}
                    </Button>
                </div>
            )}

            {step === 'muscle' && (
                <div className="space-y-4">
                    <h2 className="text-lg font-bold text-[#F5F5F5]">
                        {t('member.builder.step_muscle')}
                    </h2>
                    <BodyPartPicker
                        bodyParts={bodyParts}
                        value={muscles}
                        onChange={setMuscles}
                    />
                    <StepNav
                        onBack={() => setStep('start')}
                        onNext={() => setStep('equipment')}
                        nextLabel={t('member.builder.continue')}
                    />
                </div>
            )}

            {step === 'equipment' && (
                <div className="space-y-4">
                    <h2 className="text-lg font-bold text-[#F5F5F5]">
                        {t('member.exercise.choose_equipment')}
                    </h2>
                    <EquipmentPicker
                        equipment={equipment}
                        value={equipmentSel}
                        onChange={setEquipmentSel}
                    />
                    <StepNav
                        onBack={() => setStep('muscle')}
                        onNext={preview}
                        nextLabel={t('member.builder.continue')}
                        busy={busy}
                    />
                </div>
            )}

            {step === 'exercises' && built !== null && (
                <div className="space-y-4 pb-24">
                    <div>
                        <h2 className="text-lg font-bold text-[#F5F5F5]">
                            {built.playlist.name}
                        </h2>
                        <p className="mt-1 text-sm text-[#8E8E93]">
                            {t('member.builder.exercises_count', {
                                count: built.playlist.exercise_ids.length,
                            })}
                        </p>
                    </div>

                    <div className="divide-y divide-[#2E2E2E]">
                        {built.playlist.exercise_ids.map((id, index) => {
                            const summary = built.summaries[id];

                            return (
                                <Link
                                    key={id}
                                    href={exerciseShow(id).url}
                                    className="-mx-2 flex items-center gap-3 rounded-xl px-2 py-3.5 transition-colors hover:bg-[#1F1F1F]"
                                >
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full border border-[#2E2E2E] bg-[#141414] text-xs font-bold text-[#8E8E93]">
                                        {index + 1}
                                    </span>
                                    {summary?.image_url !== undefined ? (
                                        <span className="shrink-0 overflow-hidden rounded-xl border border-[#2E2E2E] bg-white">
                                            <img
                                                src={summary.image_url}
                                                alt=""
                                                loading="lazy"
                                                className="size-12 object-cover"
                                            />
                                        </span>
                                    ) : (
                                        <span className="size-12 shrink-0 rounded-xl border border-[#2E2E2E] bg-[#1F1F1F]" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                                            {exerciseName(built.summaries, id)}
                                        </p>
                                        <p className="truncate text-xs text-[#8E8E93]">
                                            {exerciseDetail(
                                                built.summaries,
                                                id,
                                            ) ?? ''}
                                        </p>
                                    </div>
                                    <ChevronRight className="size-4 shrink-0 text-[#8E8E93]" />
                                </Link>
                            );
                        })}
                    </div>

                    <button
                        type="button"
                        onClick={() => setStep('equipment')}
                        className="flex items-center gap-1 text-xs font-semibold text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
                    >
                        <ArrowLeft className="size-3.5" />
                        {t('member.builder.back')}
                    </button>

                    <div className="fixed inset-x-0 bottom-20 z-40 mx-auto max-w-md px-4">
                        <Button
                            className="h-12 w-full justify-center rounded-2xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black shadow-[0_4px_16px_rgba(175,250,1,0.3)] hover:brightness-105"
                            onClick={start}
                            disabled={busy}
                        >
                            <Play className="size-4 fill-current" />
                            {t('member.home.start_workout')}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

function StepNav({
    onBack,
    onNext,
    nextLabel,
    busy = false,
}: {
    onBack: () => void;
    onNext: () => void;
    nextLabel: string;
    busy?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex items-center gap-2 pt-1">
            <button
                type="button"
                onClick={onBack}
                className="flex items-center gap-1 text-xs font-semibold text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
            >
                <ArrowLeft className="size-3.5" />
                {t('member.builder.back')}
            </button>
            <Button
                className="ml-auto h-11 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-5 font-bold text-black hover:brightness-105"
                onClick={onNext}
                disabled={busy}
            >
                {nextLabel}
                <ChevronRight className="size-4" />
            </Button>
        </div>
    );
}
