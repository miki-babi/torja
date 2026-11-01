import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type {
    BodyPart,
    ExerciseSummaries,
} from '@/components/member/exercise-data';
import { MobileShell } from '@/components/member/mobile-shell';
import type {
    ExercisePlaylist,
    WorkoutSession,
} from '@/components/member/types';
import { WorkoutBuilder } from '@/components/member/workout-builder';
import { WorkoutSessionCard } from '@/components/member/workout-session';
import { useTranslation } from '@/lib/i18n';

export default function MemberToday({
    bodyParts,
    equipment,
    playlists: initialPlaylists,
    sessions: initialSessions,
    exerciseSummaries,
}: {
    bodyParts: BodyPart[];
    equipment: string[];
    playlists: ExercisePlaylist[];
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
}) {
    const { url } = usePage();
    const { t } = useTranslation();
    const [playlists, setPlaylists] =
        useState<ExercisePlaylist[]>(initialPlaylists);
    const [sessions, setSessions] = useState<WorkoutSession[]>(initialSessions);
    const [activeSessionId, setActiveSessionId] = useState<number | null>(
        () => {
            const param = new URLSearchParams(url.split('?')[1] ?? '').get(
                'session',
            );
            return param === null ? null : Number(param);
        },
    );

    const todayKey = new Date().toISOString().slice(0, 10);
    const todaysSessions = sessions.filter((s) => s.date === todayKey);
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

    function onBuilt(session: WorkoutSession, playlist: ExercisePlaylist) {
        setPlaylists((list) =>
            list.some((p) => p.id === playlist.id)
                ? list.map((p) => (p.id === playlist.id ? playlist : p))
                : [playlist, ...list],
        );
        upsertSession(session);
    }

    return (
        <MobileShell>
            <Head title={t('member.builder.title')} />
            <h1 className="text-xl font-semibold tracking-tight">
                {t('member.builder.title')}
            </h1>

            <div className="mt-4">
                {activeSession === null ? (
                    <WorkoutBuilder
                        bodyParts={bodyParts}
                        equipment={equipment}
                        todaysSessions={todaysSessions}
                        playlists={playlists}
                        onResume={(session) => setActiveSessionId(session.id)}
                        onStart={onBuilt}
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
