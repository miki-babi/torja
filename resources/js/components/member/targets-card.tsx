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
                    <div className="mx-auto mt-2 h-2 w-full max-w-xs overflow-hidden rounded-full bg-[#2E2E2E]">
                        <div
                            className="h-full rounded-full bg-gradient-to-r from-[#D7F457] to-[#AFFA01] shadow-[0_0_8px_rgba(175,250,1,0.3)] transition-all"
                            style={{ width: `${kcalPercent}%` }}
                        />
                    </div>
                </div>
                <div className="space-y-3">
                    {rows.map(({ label, value, target }) => (
                        <div key={label} className="grid gap-1.5">
                            <div className="flex justify-between text-xs font-semibold">
                                <span className="text-[#8E8E93]">
                                    {label}
                                </span>
                                <span className="font-mono text-[#F5F5F5]">
                                    {Math.round(value)}g / {target}g
                                </span>
                            </div>
                            <div className="h-1.5 overflow-hidden rounded-full bg-[#2E2E2E]">
                                <div
                                    className="h-full rounded-full bg-[#AFFA01] transition-all"
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
