import type { DiaryTotals, NutritionTargets } from '@/components/member/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function TargetsCard({
    targets,
    consumed,
}: {
    targets: NutritionTargets;
    consumed: DiaryTotals;
}) {
    const kcalPercent = Math.min(
        100,
        (consumed.kcal / targets.kcal_target) * 100,
    );

    const rows = [
        {
            label: 'Protein',
            value: consumed.protein,
            target: targets.protein_g,
        },
        { label: 'Carbs', value: consumed.carbs, target: targets.carbs_g },
        { label: 'Fat', value: consumed.fat, target: targets.fat_g },
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Today vs targets</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="text-center">
                    <p className="text-3xl font-semibold tracking-tight">
                        {Math.round(consumed.kcal)}
                        <span className="text-base font-normal text-muted-foreground">
                            {' '}
                            / {targets.kcal_target} kcal
                        </span>
                    </p>
                    <div className="mx-auto mt-2 h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-lime-400"
                            style={{ width: `${kcalPercent}%` }}
                        />
                    </div>
                </div>
                <div className="space-y-2">
                    {rows.map(({ label, value, target }) => (
                        <div key={label} className="grid gap-1">
                            <div className="flex justify-between text-xs">
                                <span className="text-muted-foreground">
                                    {label}
                                </span>
                                <span>
                                    {Math.round(value)}g / {target}g
                                </span>
                            </div>
                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-lime-400"
                                    style={{
                                        width: `${Math.min(100, (value / target) * 100)}%`,
                                    }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
