import InputError from '@/components/input-error';
import { ChipMulti } from '@/components/plan/chip-multi';
import {
    SearchablePicker,
    type PickerItem,
} from '@/components/plan/searchable-picker';
import type { StepProps } from '@/components/plan/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    ALLERGEN_OPTIONS,
    optionLabel,
    type AvoidancesAnswers,
} from '@/lib/plan-options';
import { useMemo } from 'react';

export default function Step08Avoidances({
    value,
    onChange,
    errors,
    picker,
}: StepProps<AvoidancesAnswers>) {
    const ingredientItems: PickerItem[] = useMemo(
        () =>
            (picker?.ingredients ?? []).map((ingredient) => ({
                id: ingredient.id,
                label: ingredient.name_en,
                sublabel:
                    ingredient.allergens
                        .map((code) => optionLabel(ALLERGEN_OPTIONS, code))
                        .join(', ') || undefined,
            })),
        [picker],
    );

    const foodItems: PickerItem[] = useMemo(() => {
        const items: PickerItem[] = [];
        for (const group of picker?.foodGroups ?? []) {
            for (const category of group.categories) {
                for (const food of category.foods) {
                    items.push({
                        id: food.id,
                        label: food.name_en,
                        sublabel: category.name,
                    });
                }
            }
        }
        return items;
    }, [picker]);

    function toggleId(
        key:
            | 'disliked_food_ids'
            | 'excluded_ingredient_ids'
            | 'discomfort_ingredient_ids',
        id: number,
    ) {
        const list = value[key];
        onChange({
            [key]: list.includes(id)
                ? list.filter((item) => item !== id)
                : [...list, id],
        } as Partial<AvoidancesAnswers>);
    }

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>Do you have any allergies?</Label>
                <p className="text-xs text-muted-foreground">
                    Select the allergens you react to — we'll filter them out of
                    your plan.
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

            {!picker ? (
                <div className="space-y-3">
                    <Skeleton className="h-9 w-full" />
                    <Skeleton className="h-32 w-full" />
                    <Skeleton className="h-32 w-full" />
                </div>
            ) : (
                <>
                    <div className="grid gap-2">
                        <Label>Ingredients you can't have</Label>
                        <SearchablePicker
                            items={ingredientItems}
                            selectedIds={value.excluded_ingredient_ids}
                            onToggle={(id) =>
                                toggleId('excluded_ingredient_ids', id)
                            }
                            placeholder="Search ingredients…"
                        />
                        <InputError message={errors.excluded_ingredient_ids} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Foods you just don't like</Label>
                        <SearchablePicker
                            items={foodItems}
                            selectedIds={value.disliked_food_ids}
                            onToggle={(id) => toggleId('disliked_food_ids', id)}
                            placeholder="Search foods…"
                        />
                        <InputError message={errors.disliked_food_ids} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Foods that cause discomfort (optional)</Label>
                        <SearchablePicker
                            items={ingredientItems}
                            selectedIds={value.discomfort_ingredient_ids}
                            onToggle={(id) =>
                                toggleId('discomfort_ingredient_ids', id)
                            }
                            placeholder="e.g. foods causing bloating…"
                        />
                        <InputError
                            message={errors.discomfort_ingredient_ids}
                        />
                    </div>
                </>
            )}

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
