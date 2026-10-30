import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { useUnitSystem } from '@/components/calculator/use-unit-system';
import type { Sex } from '@/lib/body-metrics';

export function MeasurementFields({
    u,
    sex,
    invalid,
    idPrefix = '',
}: {
    u: ReturnType<typeof useUnitSystem>;
    sex: Sex;
    invalid: boolean;
    idPrefix?: string;
}) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}neck`}>
                        Neck ({u.lengthUnit})
                    </Label>
                    <Input
                        id={`${idPrefix}neck`}
                        type="number"
                        inputMode="decimal"
                        min="0"
                        step="any"
                        value={u.neck}
                        onChange={(e) => u.setNeck(e.target.value)}
                        placeholder={u.isMetric ? '38' : '15'}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}waist`}>
                        Waist ({u.lengthUnit})
                    </Label>
                    <Input
                        id={`${idPrefix}waist`}
                        type="number"
                        inputMode="decimal"
                        min="0"
                        step="any"
                        value={u.waist}
                        onChange={(e) => u.setWaist(e.target.value)}
                        placeholder={u.isMetric ? '85' : '33'}
                    />
                </div>
                {sex === 'female' && (
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}hip`}>
                            Hip ({u.lengthUnit})
                        </Label>
                        <Input
                            id={`${idPrefix}hip`}
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="any"
                            value={u.hip}
                            onChange={(e) => u.setHip(e.target.value)}
                            placeholder={u.isMetric ? '95' : '37'}
                        />
                    </div>
                )}
            </div>
            {invalid && (
                <p className="text-sm text-muted-foreground">
                    {sex === 'male'
                        ? 'Waist must be larger than neck.'
                        : 'Waist plus hip must be larger than neck.'}
                </p>
            )}
        </>
    );
}
