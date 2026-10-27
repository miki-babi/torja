import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { MobileShell } from '@/components/member/mobile-shell';
import type { WorkoutSession } from '@/components/member/types';
import {
    type PlayerWorkout,
    WorkoutPlayer,
} from '@/components/member/workout-player';
import { useTranslation } from '@/lib/i18n';

export default function MemberWorkoutPlayerPage({
    program,
    workout,
    session: initialSession,
    backUrl,
}: {
    program: { name: string };
    workout: PlayerWorkout;
    session: WorkoutSession;
    backUrl: string;
}) {
    const { t } = useTranslation();
    const [session, setSession] = useState(initialSession);

    return (
        <MobileShell hideNav>
            <Head title={`${program.name} · ${workout.title}`} />
            <WorkoutPlayer
                workout={workout}
                session={session}
                onSessionChange={setSession}
                onExit={() => router.visit(backUrl)}
                t={t}
            />
        </MobileShell>
    );
}
