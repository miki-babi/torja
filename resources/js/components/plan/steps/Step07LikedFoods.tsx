import InputError from '@/components/input-error';
import type { StepProps } from '@/components/plan/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    FOOD_GROUP_LABELS,
    LIKED_FOOD_TAGS,
    type LikedFoodsAnswers,
    type PickerFood,
} from '@/lib/plan-options';
import { cn } from '@/lib/utils';
import { Apple, Drumstick, UtensilsCrossed, Wheat, X } from 'lucide-react';
import { useMemo, useState } from 'react';

const GROUP_ICONS: Record<string, typeof Apple> = {
    traditional_ethiopian: UtensilsCrossed,
    protein_rich: Drumstick,
    other_staples: Wheat,
    fruit_and_veg: Apple,
};

export default function Step07LikedFoods({
    value,
    onChange,
    errors,
    picker,
}: StepProps<LikedFoodsAnswers>) {
    const [query, setQuery] = useState('');

    const foodsById = useMemo(() => {
        const map = new Map<number, PickerFood>();
        for (const group of picker?.foodGroups ?? []) {
            for (const category of group.categories) {
                for (const food of category.foods) {
                    map.set(food.id, food);
                }
            }
        }
        return map;
    }, [picker]);

    function foodName(food: PickerFood | undefined, id: number): string {
        return food?.name_en ?? `Food #${id}`;
    }

    function toggleFood(id: number) {
        const existing = value.liked_foods.find((item) => item.food_id === id);
        if (existing) {
            onChange({
                liked_foods: value.liked_foods.filter(
                    (item) => item.food_id !== id,
                ),
            });
        } else {
            onChange({
                liked_foods: [
                    ...value.liked_foods,
                    { food_id: id, tags: ['enjoy'] },
                ],
            });
        }
    }

    function toggleTag(foodId: number, tag: string) {
        onChange({
            liked_foods: value.liked_foods.map((item) => {
                if (item.food_id !== foodId) {
                    return item;
                }
                if (item.tags.includes(tag)) {
                    // Keep at least one tag per food.
                    if (item.tags.length === 1) {
                        return item;
                    }
                    return {
                        ...item,
                        tags: item.tags.filter((t) => t !== tag),
                    };
                }
                return { ...item, tags: [...item.tags, tag] };
            }),
        });
    }

    if (!picker) {
        return (
            <div className="space-y-3">
                <Skeleton className="h-9 w-full" />
                <Skeleton className="h-24 w-full" />
                <Skeleton className="h-24 w-full" />
            </div>
        );
    }

    const needle = query.trim().toLowerCase();
    const likedIds = new Set(value.liked_foods.map((item) => item.food_id));

    return (
        <div className="space-y-6">
            <Input
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search foods (English or Amharic)…"
            />

            {value.liked_foods.length > 0 && (
                <div className="grid gap-2">
                    <Label>
                        Your picks ({value.liked_foods.length}) — tag how you
                        feel about each
                    </Label>
                    <div className="space-y-2 rounded-md border border-border p-3">
                        {value.liked_foods.map((item) => (
                            <div
                                key={item.food_id}
                                className="flex flex-wrap items-center gap-2"
                            >
                                <button
                                    type="button"
                                    onClick={() => toggleFood(item.food_id)}
                                    className="inline-flex items-center gap-1 rounded-full border border-foreground bg-foreground px-2.5 py-1 text-xs text-background"
                                >
                                    {foodName(
                                        foodsById.get(item.food_id),
                                        item.food_id,
                                    )}
                                    <X className="size-3" />
                                </button>
                                {LIKED_FOOD_TAGS.map((tag) => {
                                    const active = item.tags.includes(
                                        tag.value,
                                    );
                                    return (
                                        <button
                                            key={tag.value}
                                            type="button"
                                            onClick={() =>
                                                toggleTag(
                                                    item.food_id,
                                                    tag.value,
                                                )
                                            }
                                            className={cn(
                                                'rounded-full border px-2 py-0.5 text-xs transition-colors',
                                                active
                                                    ? 'border-foreground bg-muted'
                                                    : 'border-border text-muted-foreground hover:border-foreground/40',
                                            )}
                                        >
                                            {tag.label}
                                        </button>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {picker.foodGroups.map((group) => {
                const Icon = GROUP_ICONS[group.section] ?? Apple;
                const categories = group.categories
                    .map((category) => ({
                        ...category,
                        foods: needle
                            ? category.foods.filter(
                                  (food) =>
                                      food.name_en
                                          .toLowerCase()
                                          .includes(needle) ||
                                      (food.name_am ?? '').includes(
                                          query.trim(),
                                      ),
                              )
                            : category.foods,
                    }))
                    .filter((category) => category.foods.length > 0);

                if (categories.length === 0) {
                    return null;
                }

                return (
                    <div key={group.section} className="grid gap-3">
                        <Label className="flex items-center gap-2">
                            <Icon className="size-4" />
                            {FOOD_GROUP_LABELS[group.section] ?? group.section}
                        </Label>
                        {categories.map((category) => (
                            <div key={category.name} className="grid gap-1.5">
                                <p className="text-xs font-medium text-muted-foreground">
                                    {category.name}
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {category.foods.map((food) => {
                                        const selected = likedIds.has(food.id);
                                        return (
                                            <button
                                                key={food.id}
                                                type="button"
                                                onClick={() =>
                                                    toggleFood(food.id)
                                                }
                                                className={cn(
                                                    'rounded-full border px-2.5 py-1 text-xs transition-colors',
                                                    selected
                                                        ? 'border-foreground bg-foreground text-background'
                                                        : 'border-border hover:border-foreground/40',
                                                )}
                                            >
                                                {food.name_en}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                );
            })}

            <InputError message={errors.liked_foods} />
        </div>
    );
}
