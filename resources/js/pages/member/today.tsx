import { Head, router, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import type { CatalogProgram } from '@/components/member/discover-programs';
import type {
    BodyPart,
    ExerciseSummaries,
} from '@/components/member/exercise-data';
import { MobileShell } from '@/components/member/mobile-shell';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import {
    WORKOUT_BUILDER_STORAGE_KEY,
    WorkoutBuilder,
} from '@/components/member/workout-builder';
import { WorkoutSessionCard } from '@/components/member/workout-session';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';

export default function MemberToday({
    bodyParts,
    equipment,
    playlists: initialPlaylists,
    sessions: initialSessions,
    exerciseSummaries,
    exercisePrograms,
    backUrl,
}: {
    bodyParts: BodyPart[];
    equipment: string[];
    playlists: ExercisePlaylist[];
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
    exercisePrograms: CatalogProgram[];
    backUrl: string;
}) {
    const { url } = usePage();
    const { t } = useTranslation();
    const [playlists] = useState<ExercisePlaylist[]>(initialPlaylists);
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [activeSessionId, setActiveSessionId] = useState<number | null>(
        () => {
            const param = new URLSearchParams(url.split('?')[1] ?? '').get(
                'session',
            );
            return param === null ? null : Number(param);
        },
    );

    const activeSession =
        sessions.find((s) => s.id === activeSessionId) ?? null;
    const activePlaylist =
        playlists.find((p) => p.id === activeSession?.exercise_playlist_id) ??
        null;

    function upsertSession(next: WorkoutSession) {
        setSessions((list) =>
            list.some((s) => s.id === next.id)
                ? list.map((s) => (s.id === next.id ? next : s))
                : [next, ...list],
        );
        setActiveSessionId(next.id);
    }

    function closeWizard() {
        try {
            sessionStorage.removeItem(WORKOUT_BUILDER_STORAGE_KEY);
        } catch {
            // Storage unavailable — nothing to reset.
        }
        router.visit(backUrl);
    }

    return (
        <MobileShell hideNav>
            <Head title={t('member.builder.title')} />
            <div className="flex items-center gap-1">
                {activeSession === null && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="-ml-2 size-8 shrink-0"
                        onClick={closeWizard}
                        aria-label={t('member.builder.back')}
                    >
                        <ChevronLeft className="size-5" />
                    </Button>
                )}
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('member.builder.title')}
                </h1>
            </div>

            <div className="mt-4">
                {activeSession === null ? (
                    <WorkoutBuilder
                        bodyParts={bodyParts}
                        equipment={equipment}
                        exercisePrograms={exercisePrograms}
                    />
                ) : (
                    <WorkoutSessionCard
                        session={activeSession}
                        playlist={activePlaylist}
                        sessions={sessions}
                        exerciseSummaries={exerciseSummaries}
                        onSessionChange={upsertSession}
                        onBack={() => setActiveSessionId(null)}
                    />
                )}
            </div>
        </MobileShell>
    );
}
