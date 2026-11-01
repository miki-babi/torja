import InputError from '@/components/input-error';
import { ChipMulti } from '@/components/plan/chip-multi';
import { TextChips } from '@/components/plan/text-chips';
import type { StepProps } from '@/components/plan/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ALLERGEN_OPTIONS, type AvoidancesAnswers } from '@/lib/plan-options';

export default function Step08Avoidances({
    value,
    onChange,
    errors,
}: StepProps<AvoidancesAnswers>) {
    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>Do you have any allergies?</Label>
                <p className="text-xs text-muted-foreground">
                    Select the allergens you react to.
                </p>
                <ChipMulti
                    options={ALLERGEN_OPTIONS}
                    values={value.allergen_codes}
                    onChange={(allergen_codes) => onChange({ allergen_codes })}
                />
                <InputError message={errors.allergen_codes} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="allergy_other">
                    Other allergies or notes (optional)
                </Label>
                <Input
                    id="allergy_other"
                    value={value.allergy_other}
                    onChange={(event) =>
                        onChange({ allergy_other: event.target.value })
                    }
                    placeholder="e.g. Sensitive to very spicy food"
                />
                <InputError message={errors.allergy_other} />
            </div>

            <div className="grid gap-2">
                <Label>Ingredients you can't have</Label>
                <TextChips
                    values={value.excluded_ingredients}
                    onChange={(excluded_ingredients) =>
                        onChange({ excluded_ingredients })
                    }
                    placeholder="e.g. Butter, berbere…"
                />
                <InputError message={errors.excluded_ingredients} />
            </div>

            <div className="grid gap-2">
                <Label>Foods you just don't like</Label>
                <TextChips
                    values={value.disliked_foods}
                    onChange={(disliked_foods) => onChange({ disliked_foods })}
                    placeholder="e.g. Kale, fish…"
                />
                <InputError message={errors.disliked_foods} />
            </div>

            <div className="grid gap-2">
                <Label>Foods that cause discomfort (optional)</Label>
                <TextChips
                    values={value.discomfort_ingredients}
                    onChange={(discomfort_ingredients) =>
                        onChange({ discomfort_ingredients })
                    }
                    placeholder="e.g. Beans, cabbage…"
                />
                <InputError message={errors.discomfort_ingredients} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="discomfort_note">
                    Discomfort notes (optional)
                </Label>
                <Input
                    id="discomfort_note"
                    value={value.discomfort_note}
                    onChange={(event) =>
                        onChange({ discomfort_note: event.target.value })
                    }
                    placeholder="e.g. Beans give me gas"
                />
                <InputError message={errors.discomfort_note} />
            </div>
        </div>
    );
}
