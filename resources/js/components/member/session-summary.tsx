import {
    exerciseName,
    type ExerciseSummaries,
} from '@/components/member/exercise-data';
import type { WorkoutSession } from '@/components/member/types';
import {
    prByExercise,
    sessionsThisWeek,
    totalVolume,
} from '@/components/member/workout-progress';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function SessionSummary({
    sessions,
    exerciseSummaries,
}: {
    sessions: WorkoutSession[];
    exerciseSummaries: ExerciseSummaries;
}) {
    const weekCount = sessionsThisWeek(sessions);
    const volume = totalVolume(sessions);
    const prs = Object.entries(prByExercise(sessions))
        .sort((a, b) => b[1].weight_kg - a[1].weight_kg)
        .slice(0, 3);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Progress</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-2.5 text-center">
                    <div className="rounded-2xl border border-[#2E2E2E] bg-[#262626] py-3.5">
                        <p className="text-2xl font-black text-[#AFFA01]">
                            {weekCount}
                        </p>
                        <p className="mt-0.5 text-xs font-medium text-[#A1A1AA]">
                            sessions this week
                        </p>
                    </div>
                    <div className="rounded-2xl border border-[#2E2E2E] bg-[#262626] py-3.5">
                        <p className="text-2xl font-black text-[#F5F5F5]">
                            {Math.round(volume).toLocaleString()}
                        </p>
                        <p className="mt-0.5 text-xs font-medium text-[#A1A1AA]">
                            kg total volume
                        </p>
                    </div>
                </div>

                {prs.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-semibold tracking-wider text-[#A1A1AA] uppercase">
                            Personal records 🏆
                        </p>
                        <div className="divide-y divide-[#2E2E2E]">
                            {prs.map(([exerciseId, pr]) => (
                                <div
                                    key={exerciseId}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <span className="font-medium text-[#F5F5F5]">
                                        {exerciseName(
                                            exerciseSummaries,
                                            exerciseId,
                                        )}
                                    </span>
                                    <span className="font-semibold text-[#AFFA01]">
                                        {pr.weight_kg > 0
                                            ? `${pr.weight_kg}kg × ${pr.reps}`
                                            : `${pr.reps} reps`}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
