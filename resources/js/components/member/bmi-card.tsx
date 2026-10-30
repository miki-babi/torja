import { ChevronDown } from 'lucide-react';
import { BodyFields } from '@/components/calculator/body-fields';
import { ResultStat } from '@/components/calculator/result-stat';
import { UnitToggle } from '@/components/calculator/unit-toggle';
import { useUnitSystem } from '@/components/calculator/use-unit-system';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { bmiCategory, calculateBmi } from '@/lib/body-metrics';

export function BmiCard() {
    const u = useUnitSystem();
    const bmi = calculateBmi(u.weightKg, u.heightCm);

    return (
        <Collapsible>
            <Card>
                <CollapsibleTrigger className="group w-full text-left">
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle className="text-base">BMI</CardTitle>
                        <div className="flex items-center gap-2">
                            {bmi !== null && (
                                <span className="text-xs font-semibold text-[#AFFA01]">
                                    {bmi.toFixed(1)}
                                </span>
                            )}
                            <ChevronDown className="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                        </div>
                    </CardHeader>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <CardContent className="space-y-4">
                        <UnitToggle
                            value={u.unit}
                            onChange={u.handleUnitChange}
                        />
                        <BodyFields u={u} idPrefix="bmi-" />
                        <ResultStat
                            label="Body Mass Index"
                            value={bmi === null ? '—' : bmi.toFixed(1)}
                            badge={bmi === null ? undefined : bmiCategory(bmi)}
                        />
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    );
}
