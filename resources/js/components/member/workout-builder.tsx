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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
 * equipment → start. The server generates a playlist matching the picks and
 * opens a session on it. Empty selections mean "full body" / "any
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
        <Card>
            <CardHeader>
                <p className="text-[11px] font-bold tracking-wider text-[#AFFA01] uppercase">
                    {stepLabel}
                </p>
                <CardTitle className="text-base">
                    {step === 'start' && t('member.builder.title')}
                    {step === 'muscle' && t('member.builder.step_muscle')}
                    {step === 'equipment' &&
                        t('member.exercise.choose_equipment')}
                </CardTitle>
                {step === 'start' && (
                    <CardDescription>
                        {t('member.builder.subtitle')}
                    </CardDescription>
                )}
            </CardHeader>
            <CardContent className="space-y-3">
                {step === 'start' && (
                    <Button
                        className="h-12 w-full justify-center rounded-2xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] font-bold text-black hover:brightness-105"
                        onClick={() => setStep('muscle')}
                    >
                        <Play className="size-4 fill-current" />
                        {t('member.home.start_workout')}
                    </Button>
                )}

                {step === 'muscle' && (
                    <>
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
                    </>
                )}

                {step === 'equipment' && (
                    <>
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
                    </>
                )}
            </CardContent>
        </Card>
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
                className="ml-auto h-10 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-5 font-bold text-black hover:brightness-105"
                onClick={onNext}
                disabled={busy}
            >
                {nextLabel}
                <ChevronRight className="size-4" />
            </Button>
        </div>
    );
}
