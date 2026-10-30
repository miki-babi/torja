import { fatMassKg, leanMassKg } from '@/lib/body-metrics';

export function MassStats({
    weightKg,
    bodyFatPercent,
    toDisplayWeight,
    weightUnit,
}: {
    weightKg: number;
    bodyFatPercent: number;
    toDisplayWeight: (kg: number) => number;
    weightUnit: string;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div>
                <p className="text-sm text-muted-foreground">Fat mass</p>
                <p className="mt-1 text-xl font-medium">
                    {toDisplayWeight(
                        fatMassKg(weightKg, bodyFatPercent),
                    ).toFixed(1)}{' '}
                    {weightUnit}
                </p>
            </div>
            <div>
                <p className="text-sm text-muted-foreground">Lean mass</p>
                <p className="mt-1 text-xl font-medium">
                    {toDisplayWeight(
                        leanMassKg(weightKg, bodyFatPercent),
                    ).toFixed(1)}{' '}
                    {weightUnit}
                </p>
            </div>
        </div>
    );
}
