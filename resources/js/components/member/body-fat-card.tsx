import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { BodyFields } from '@/components/calculator/body-fields';
import { CheckboxChoice } from '@/components/calculator/checkbox-choice';
import { MeasurementFields } from '@/components/calculator/measurement-fields';
import { ResultStat } from '@/components/calculator/result-stat';
import { SexToggle } from '@/components/calculator/sex-toggle';
import { UnitToggle } from '@/components/calculator/unit-toggle';
import {
    parseNumber,
    useUnitSystem,
} from '@/components/calculator/use-unit-system';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    bmiBodyFat,
    bodyFatCategory,
    calculateBmi,
    navyBodyFat,
    type BodyFatMethod,
    type Sex,
} from '@/lib/body-metrics';

export function BodyFatCard() {
    const u = useUnitSystem();
    const [sex, setSex] = useState<Sex>('male');
    const [method, setMethod] = useState<BodyFatMethod>('navy');
    const [age, setAge] = useState('');

    const bmi = calculateBmi(u.weightKg, u.heightCm);
    const percent =
        method === 'navy'
            ? navyBodyFat({
                  sex,
                  heightCm: u.heightCm,
                  neckCm: u.toCm(u.neck),
                  waistCm: u.toCm(u.waist),
                  hipCm: sex === 'female' ? u.toCm(u.hip) : undefined,
              })
            : bmiBodyFat({ bmi: bmi ?? 0, age: parseNumber(age), sex });

    const navyInvalid =
        method === 'navy' &&
        u.heightCm > 0 &&
        percent === null &&
        (parseNumber(u.waist) > 0 || parseNumber(u.neck) > 0);

    return (
        <Collapsible>
            <Card>
                <CollapsibleTrigger className="group w-full text-left">
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle className="text-base">Body Fat</CardTitle>
                        <div className="flex items-center gap-2">
                            {percent !== null && (
                                <span className="text-xs font-semibold text-[#AFFA01]">
                                    {percent.toFixed(1)}%
                                </span>
                            )}
                            <ChevronDown className="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                        </div>
                    </CardHeader>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Method</Label>
                            <CheckboxChoice
                                value={method}
                                onChange={setMethod}
                                options={[
                                    { value: 'navy', label: 'US Navy' },
                                    { value: 'bmi', label: 'BMI Estimate' },
                                ]}
                            />
                        </div>

                        <SexToggle value={sex} onChange={setSex} />
                        <UnitToggle
                            value={u.unit}
                            onChange={u.handleUnitChange}
                        />
                        <BodyFields u={u} idPrefix="bf-" />

                        {method === 'navy' ? (
                            <MeasurementFields
                                u={u}
                                sex={sex}
                                invalid={navyInvalid}
                                idPrefix="bf-"
                            />
                        ) : (
                            <div className="grid gap-2">
                                <Label htmlFor="bf-age">Age</Label>
                                <Input
                                    id="bf-age"
                                    type="number"
                                    inputMode="numeric"
                                    min="0"
                                    step="any"
                                    value={age}
                                    onChange={(e) => setAge(e.target.value)}
                                    placeholder="30"
                                />
                            </div>
                        )}

                        <ResultStat
                            label={
                                method === 'navy'
                                    ? 'Body Fat (US Navy)'
                                    : 'Body Fat (BMI Estimate)'
                            }
                            value={
                                percent === null
                                    ? '—'
                                    : `${percent.toFixed(1)}%`
                            }
                            badge={
                                percent === null
                                    ? undefined
                                    : bodyFatCategory(percent, sex)
                            }
                        />
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    );
}
