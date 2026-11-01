import { ArrowLeft, ChevronRight, Play } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { BodyPartPicker } from '@/components/member/body-part-picker';
import { EquipmentPicker } from '@/components/member/equipment-picker';
import type { BodyPart } from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { build } from '@/routes/member/workouts';

type Step = 'start' | 'muscle' | 'equipment';

const STEP_INDEX: Record<Step, number> = {
    start: 1,
    muscle: 2,
    equipment: 3,
};

/**
 * Three-step workout builder: start → pick target muscles → pick available
 * equipment → start. Each step fills the page like the exercise page's
 * picker sections. The server generates a playlist matching the picks and
 * opens a session on it; empty selections mean "full body" / "any
 * equipment".
 */
export function WorkoutBuilder({
    bodyParts,
    equipment,
    onStart,
}: {
    bodyParts: BodyPart[];
    equipment: string[];
    onStart: (session: WorkoutSession, playlist: ExercisePlaylist) => void;
}) {
    const { t } = useTranslation();
    const [step, setStep] = useState<Step>('start');
    const [muscles, setMuscles] = useState<string[]>([]);
    const [equipmentSel, setEquipmentSel] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);

    async function start() {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const { session, playlist } = await memberApi<{
                session: WorkoutSession;
                playlist: ExercisePlaylist;
            }>(build().url, 'POST', {
                muscles,
                equipment: equipmentSel,
            });
            onStart(session, playlist);
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

    const stepLabel = t('member.builder.step', {
        current: STEP_INDEX[step],
        total: 3,
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
                        onNext={start}
                        nextLabel={t('member.home.start_workout')}
                        busy={busy}
                    />
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
