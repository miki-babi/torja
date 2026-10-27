import InputError from '@/components/input-error';
import { ChoiceCards } from '@/components/plan/choice-card';
import { ChipMulti } from '@/components/plan/chip-multi';
import type { StepProps } from '@/components/plan/types';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    BUDGET_TYPES,
    FOOD_SOURCES,
    INGREDIENT_AVAILABILITY,
    type BudgetAnswers,
} from '@/lib/plan-options';

export default function Step11Budget({
    value,
    onChange,
    errors,
}: StepProps<BudgetAnswers>) {
    const hasBudget =
        value.budget_type === 'daily' || value.budget_type === 'weekly';

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>Do you have a food budget?</Label>
                <ChoiceCards
                    options={BUDGET_TYPES}
                    value={value.budget_type}
                    onChange={(budget_type) => onChange({ budget_type })}
                />
                <InputError message={errors.budget_type} />
            </div>

            {hasBudget && (
                <div className="grid gap-2">
                    <Label htmlFor="budget_amount_etb">
                        Budget amount in ETB (
                        {value.budget_type === 'daily' ? 'per day' : 'per week'}
                        )
                    </Label>
                    <Input
                        id="budget_amount_etb"
                        type="number"
                        inputMode="decimal"
                        min="0"
                        step="any"
                        value={value.budget_amount_etb}
                        onChange={(event) =>
                            onChange({
                                budget_amount_etb: event.target.value,
                            })
                        }
                        placeholder="e.g. 300"
                    />
                    <InputError message={errors.budget_amount_etb} />
                </div>
            )}

            <div className="grid gap-2">
                <Label>Where do you usually get food?</Label>
                <ChipMulti
                    options={FOOD_SOURCES}
                    values={value.food_sources}
                    onChange={(food_sources) => onChange({ food_sources })}
                />
                <InputError message={errors.food_sources} />
            </div>

            <div className="grid gap-2">
                <Label>How easy is it to find ingredients near you?</Label>
                <ChoiceCards
                    options={INGREDIENT_AVAILABILITY}
                    value={value.ingredient_availability}
                    onChange={(ingredient_availability) =>
                        onChange({ ingredient_availability })
                    }
                    columns={2}
                />
                <InputError message={errors.ingredient_availability} />
            </div>

            <Label className="flex items-start gap-2 text-sm font-normal">
                <Checkbox
                    checked={value.allow_substitutions}
                    onCheckedChange={(checked) =>
                        onChange({ allow_substitutions: checked === true })
                    }
                />
                Happy to swap ingredients when something is unavailable or
                cheaper
            </Label>
            <InputError message={errors.allow_substitutions} />
        </div>
    );
}
