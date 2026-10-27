import InputError from '@/components/input-error';
import { ChoiceCards } from '@/components/plan/choice-card';
import type { StepProps } from '@/components/plan/types';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    DIET_TYPES,
    FASTING_OPTIONS,
    type DietAnswers,
} from '@/lib/plan-options';

export default function Step06Diet({
    value,
    onChange,
    errors,
}: StepProps<DietAnswers>) {
    const fasting = value.fasting !== 'none';

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>Which best describes how you eat?</Label>
                <ChoiceCards
                    options={DIET_TYPES}
                    value={value.diet_type}
                    onChange={(diet_type) => onChange({ diet_type })}
                />
                <InputError message={errors.diet_type} />
            </div>

            {value.diet_type === 'other' && (
                <div className="grid gap-2">
                    <Label htmlFor="diet_other">Describe your diet</Label>
                    <Input
                        id="diet_other"
                        value={value.diet_other}
                        onChange={(event) =>
                            onChange({ diet_other: event.target.value })
                        }
                        placeholder="e.g. Halal, pescatarian"
                    />
                    <InputError message={errors.diet_other} />
                </div>
            )}

            <div className="grid gap-2">
                <Label>Do you follow fasting days?</Label>
                <ChoiceCards
                    options={FASTING_OPTIONS}
                    value={value.fasting}
                    onChange={(fasting) => onChange({ fasting })}
                />
                <InputError message={errors.fasting} />
            </div>

            {fasting && (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="fasting_note">
                            Tell us about your fasting pattern
                            {value.fasting !== 'custom' && ' (optional)'}
                        </Label>
                        <Input
                            id="fasting_note"
                            value={value.fasting_note}
                            onChange={(event) =>
                                onChange({ fasting_note: event.target.value })
                            }
                            placeholder="e.g. Every Wednesday and Friday, and during Lent"
                        />
                        <InputError message={errors.fasting_note} />
                    </div>

                    <Label className="flex items-start gap-2 text-sm font-normal">
                        <Checkbox
                            checked={value.adapt_to_fasting}
                            onCheckedChange={(checked) =>
                                onChange({
                                    adapt_to_fasting: checked === true,
                                })
                            }
                        />
                        Adapt my plan to fasting days (show fasting-friendly
                        meals)
                    </Label>
                    <InputError message={errors.adapt_to_fasting} />
                </>
            )}

            <div className="grid gap-2">
                <Label htmlFor="other_rules">
                    Any other food rules? (optional)
                </Label>
                <textarea
                    id="other_rules"
                    value={value.other_rules}
                    onChange={(event) =>
                        onChange({ other_rules: event.target.value })
                    }
                    rows={3}
                    placeholder="e.g. No pork, low salt, avoid spicy food at night"
                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                />
                <InputError message={errors.other_rules} />
            </div>
        </div>
    );
}
