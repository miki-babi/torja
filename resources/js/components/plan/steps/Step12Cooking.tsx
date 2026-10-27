import InputError from '@/components/input-error';
import { ChoiceCards } from '@/components/plan/choice-card';
import { ChipMulti } from '@/components/plan/chip-multi';
import type { StepProps } from '@/components/plan/types';
import { Label } from '@/components/ui/label';
import {
    COOK_OPTIONS,
    COOK_TIMES,
    KITCHEN_ITEMS,
    REPETITION_OPTIONS,
    type CookingAnswers,
} from '@/lib/plan-options';

export default function Step12Cooking({
    value,
    onChange,
    errors,
}: StepProps<CookingAnswers>) {
    const cooks = value.cook !== 'prepared';

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>Who usually cooks?</Label>
                <ChoiceCards
                    options={COOK_OPTIONS}
                    value={value.cook}
                    onChange={(cook) =>
                        onChange(
                            cook === 'prepared'
                                ? { cook, cook_time: '', kitchen: [] }
                                : { cook },
                        )
                    }
                />
                <InputError message={errors.cook} />
            </div>

            {cooks && (
                <>
                    <div className="grid gap-2">
                        <Label>
                            How much time can you spend cooking per meal?
                        </Label>
                        <ChoiceCards
                            options={COOK_TIMES}
                            value={value.cook_time}
                            onChange={(cook_time) => onChange({ cook_time })}
                            columns={2}
                        />
                        <InputError message={errors.cook_time} />
                    </div>

                    <div className="grid gap-2">
                        <Label>What's in your kitchen?</Label>
                        <ChipMulti
                            options={KITCHEN_ITEMS}
                            values={value.kitchen}
                            onChange={(kitchen) => onChange({ kitchen })}
                        />
                        <InputError message={errors.kitchen} />
                    </div>
                </>
            )}

            <div className="grid gap-2">
                <Label>How do you feel about repeating meals?</Label>
                <ChoiceCards
                    options={REPETITION_OPTIONS}
                    value={value.repetition}
                    onChange={(repetition) => onChange({ repetition })}
                />
                <InputError message={errors.repetition} />
            </div>
        </div>
    );
}
