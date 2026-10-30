import type { ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { useUnitSystem } from '@/components/calculator/use-unit-system';

export function BodyFields({
    u,
    idPrefix = '',
    children,
}: {
    u: ReturnType<typeof useUnitSystem>;
    idPrefix?: string;
    children?: ReactNode;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}weight`}>
                    Weight ({u.weightUnit})
                </Label>
                <Input
                    id={`${idPrefix}weight`}
                    type="number"
                    inputMode="decimal"
                    min="0"
                    step="any"
                    value={u.weight}
                    onChange={(e) => u.setWeight(e.target.value)}
                    placeholder={u.isMetric ? '70' : '154'}
                />
            </div>

            {u.isMetric ? (
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}height`}>Height (cm)</Label>
                    <Input
                        id={`${idPrefix}height`}
                        type="number"
                        inputMode="decimal"
                        min="0"
                        step="any"
                        value={u.height}
                        onChange={(e) => u.setHeight(e.target.value)}
                        placeholder="175"
                    />
                </div>
            ) : (
                <div className="grid gap-2">
                    <Label>Height</Label>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="grid gap-1">
                            <Input
                                id={`${idPrefix}height-ft`}
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step="any"
                                value={u.heightFt}
                                onChange={(e) => u.setHeightFt(e.target.value)}
                                placeholder="5"
                            />
                            <Label
                                htmlFor={`${idPrefix}height-ft`}
                                className="text-xs text-muted-foreground"
                            >
                                ft
                            </Label>
                        </div>
                        <div className="grid gap-1">
                            <Input
                                id={`${idPrefix}height-in`}
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step="any"
                                value={u.heightIn}
                                onChange={(e) => u.setHeightIn(e.target.value)}
                                placeholder="9"
                            />
                            <Label
                                htmlFor={`${idPrefix}height-in`}
                                className="text-xs text-muted-foreground"
                            >
                                in
                            </Label>
                        </div>
                    </div>
                </div>
            )}

            {children}
        </div>
    );
}
