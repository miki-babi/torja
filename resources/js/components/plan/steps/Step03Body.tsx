import InputError from '@/components/input-error';
import { ChoiceCards } from '@/components/plan/choice-card';
import { ChipMulti } from '@/components/plan/chip-multi';
import type { StepProps } from '@/components/plan/types';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    MEDICAL_FLAGS,
    SEX_OPTIONS,
    WEIGHT_TRENDS,
    type BodyAnswers,
} from '@/lib/plan-options';

export default function Step03Body({
    value,
    onChange,
    errors,
}: StepProps<BodyAnswers>) {
    type NumericField =
        | 'age'
        | 'height_cm'
        | 'weight_kg'
        | 'waist_cm'
        | 'body_fat_percent';

    const numberField = (
        id: NumericField,
        label: string,
        placeholder: string,
        unit?: string,
    ) => (
        <div className="grid gap-2" key={id}>
            <Label htmlFor={id}>
                {label}
                {unit && (
                    <span className="text-muted-foreground"> ({unit})</span>
                )}
            </Label>
            <Input
                id={id}
                type="number"
                inputMode="decimal"
                min="0"
                step="any"
                value={value[id] as string | number}
                onChange={(event) =>
                    onChange({
                        [id]: event.target.value,
                    } as Partial<BodyAnswers>)
                }
                placeholder={placeholder}
            />
            <InputError message={errors[id]} />
        </div>
    );

    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                {numberField('age', 'Age', 'e.g. 30')}
                {numberField('height_cm', 'Height', 'e.g. 175', 'cm')}
                {numberField('weight_kg', 'Current weight', 'e.g. 80', 'kg')}
                {numberField('waist_cm', 'Waist (optional)', 'e.g. 90', 'cm')}
            </div>

            <div className="grid gap-2">
                <Label>Sex</Label>
                <ChoiceCards
                    options={SEX_OPTIONS}
                    value={value.sex}
                    onChange={(sex) => onChange({ sex })}
                    columns={2}
                />
                <InputError message={errors.sex} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="body_fat_percent">Body fat % (optional)</Label>
                <Input
                    id="body_fat_percent"
                    type="number"
                    inputMode="decimal"
                    min="0"
                    step="any"
                    value={value.body_fat_percent}
                    onChange={(event) =>
                        onChange({ body_fat_percent: event.target.value })
                    }
                    placeholder="e.g. 22"
                />
                <InputError message={errors.body_fat_percent} />
                {value.body_fat_percent !== '' && (
                    <Label className="flex items-center gap-2 text-sm font-normal">
                        <Checkbox
                            checked={value.body_fat_is_estimate}
                            onCheckedChange={(checked) =>
                                onChange({
                                    body_fat_is_estimate: checked === true,
                                })
                            }
                        />
                        This is an estimate
                    </Label>
                )}
            </div>

            <div className="grid gap-2">
                <Label>How has your weight been lately?</Label>
                <ChoiceCards
                    options={WEIGHT_TRENDS}
                    value={value.weight_trend}
                    onChange={(weight_trend) => onChange({ weight_trend })}
                    columns={2}
                />
                <InputError message={errors.weight_trend} />
            </div>

            <div className="grid gap-2">
                <Label>Anything we should know about?</Label>
                <p className="text-xs text-muted-foreground">
                    We only warn — nothing is blocked. Talk to a professional
                    for medical diets.
                </p>
                <ChipMulti
                    options={MEDICAL_FLAGS}
                    values={value.medical_flags}
                    onChange={(medical_flags) => onChange({ medical_flags })}
                />
                <InputError message={errors.medical_flags} />
            </div>
        </div>
    );
}
