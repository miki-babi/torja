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
                <div className="grid grid-cols-2 gap-2 text-center">
                    <div className="rounded-lg bg-muted/60 py-3">
                        <p className="text-xl font-semibold">{weekCount}</p>
                        <p className="text-xs text-muted-foreground">
                            sessions this week
                        </p>
                    </div>
                    <div className="rounded-lg bg-muted/60 py-3">
                        <p className="text-xl font-semibold">
                            {Math.round(volume).toLocaleString()}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            kg total volume
                        </p>
                    </div>
                </div>

                {prs.length > 0 && (
                    <div>
                        <p className="mb-1.5 text-xs font-medium text-muted-foreground">
                            Personal records
                        </p>
                        <div className="divide-y">
                            {prs.map(([exerciseId, pr]) => (
                                <div
                                    key={exerciseId}
                                    className="flex items-center justify-between py-1.5 text-sm"
                                >
                                    <span>
                                        {exerciseName(
                                            exerciseSummaries,
                                            exerciseId,
                                        )}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {pr.weight_kg}kg × {pr.reps}
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
