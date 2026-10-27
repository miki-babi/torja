import InputError from '@/components/input-error';
import { ChoiceCards } from '@/components/plan/choice-card';
import { ChipMulti } from '@/components/plan/chip-multi';
import type { StepProps } from '@/components/plan/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    MOTIVATIONS,
    PRIMARY_GOALS,
    PRIORITIES,
    type GoalAnswers,
} from '@/lib/plan-options';

export default function Step02Goal({
    value,
    onChange,
    errors,
}: StepProps<GoalAnswers>) {
    const wantsOther = value.motivations.includes('other');

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>What is your primary goal?</Label>
                <ChoiceCards
                    options={PRIMARY_GOALS}
                    value={value.primary_goal}
                    onChange={(primary_goal) => onChange({ primary_goal })}
                />
                <InputError message={errors.primary_goal} />
            </div>

            <div className="grid gap-2">
                <Label>What motivates you? (pick all that apply)</Label>
                <ChipMulti
                    options={MOTIVATIONS}
                    values={value.motivations}
                    onChange={(motivations) => onChange({ motivations })}
                />
                <InputError message={errors.motivations} />
            </div>

            {wantsOther && (
                <div className="grid gap-2">
                    <Label htmlFor="motivation_other">
                        Tell us your motivation
                    </Label>
                    <Input
                        id="motivation_other"
                        value={value.motivation_other}
                        onChange={(event) =>
                            onChange({
                                motivation_other: event.target.value,
                            })
                        }
                        placeholder="e.g. An upcoming wedding"
                    />
                    <InputError message={errors.motivation_other} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="target_weight_kg">
                    Target weight in kg (optional)
                </Label>
                <Input
                    id="target_weight_kg"
                    type="number"
                    inputMode="decimal"
                    min="0"
                    step="any"
                    value={value.target_weight_kg}
                    onChange={(event) =>
                        onChange({ target_weight_kg: event.target.value })
                    }
                    placeholder="e.g. 70"
                />
                <InputError message={errors.target_weight_kg} />
            </div>

            <div className="grid gap-2">
                <Label>What matters most in your plan? (up to 2)</Label>
                <ChipMulti
                    options={PRIORITIES}
                    values={value.priorities}
                    onChange={(priorities) => onChange({ priorities })}
                    max={2}
                />
                <InputError message={errors.priorities} />
            </div>
        </div>
    );
}
