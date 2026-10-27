import InputError from '@/components/input-error';
import { ChipMulti } from '@/components/plan/chip-multi';
import type { StepProps } from '@/components/plan/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    IMPROVEMENTS,
    USUAL_MEALS,
    type DiaryEntry,
    type HabitsAnswers,
    type PickerFood,
} from '@/lib/plan-options';
import { Plus, X } from 'lucide-react';
import { useMemo, useState } from 'react';

const MAX_DIARY_ENTRIES = 8;

function FoodSearchField({
    entry,
    foods,
    onChange,
}: {
    entry: DiaryEntry;
    foods: PickerFood[];
    onChange: (patch: Partial<DiaryEntry>) => void;
}) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);

    const matches = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return [];
        }
        return foods
            .filter(
                (food) =>
                    food.name_en.toLowerCase().includes(needle) ||
                    (food.name_am ?? '').includes(query.trim()),
            )
            .slice(0, 8);
    }, [foods, query]);

    if (entry.food_id) {
        return (
            <div className="flex h-9 items-center justify-between gap-2 rounded-md border border-border px-3 text-sm">
                <span className="truncate">{entry._food_label}</span>
                <button
                    type="button"
                    onClick={() =>
                        onChange({ food_id: null, _food_label: undefined })
                    }
                    className="text-muted-foreground hover:text-foreground"
                    aria-label="Clear food"
                >
                    <X className="size-3.5" />
                </button>
            </div>
        );
    }

    return (
        <div className="relative">
            <Input
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => setTimeout(() => setOpen(false), 150)}
                placeholder="Search food database…"
            />
            {open && matches.length > 0 && (
                <div className="absolute z-10 mt-1 w-full rounded-md border border-border bg-popover shadow-md">
                    {matches.map((food) => (
                        <button
                            key={food.id}
                            type="button"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => {
                                onChange({
                                    food_id: food.id,
                                    _food_label: food.name_en,
                                    free_text: '',
                                });
                                setQuery('');
                                setOpen(false);
                            }}
                            className="block w-full px-3 py-2 text-left text-sm hover:bg-muted/60"
                        >
                            {food.name_en}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Step09Habits({
    value,
    onChange,
    errors,
    picker,
}: StepProps<HabitsAnswers>) {
    const allFoods = useMemo(() => {
        const foods: PickerFood[] = [];
        const seen = new Set<number>();
        for (const group of picker?.foodGroups ?? []) {
            for (const category of group.categories) {
                for (const food of category.foods) {
                    if (!seen.has(food.id)) {
                        seen.add(food.id);
                        foods.push(food);
                    }
                }
            }
        }
        return foods;
    }, [picker]);

    function updateEntry(index: number, patch: Partial<DiaryEntry>) {
        onChange({
            diary: value.diary.map((entry, i) =>
                i === index ? { ...entry, ...patch } : entry,
            ),
        });
    }

    function addEntry() {
        if (value.diary.length >= MAX_DIARY_ENTRIES) {
            return;
        }
        onChange({
            diary: [
                ...value.diary,
                {
                    meal: 'lunch',
                    food_id: null,
                    free_text: '',
                    portion_note: '',
                },
            ],
        });
    }

    function removeEntry(index: number) {
        onChange({ diary: value.diary.filter((_, i) => i !== index) });
    }

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>Which meals do you usually eat?</Label>
                <ChipMulti
                    options={USUAL_MEALS}
                    values={value.usual_meals}
                    onChange={(usual_meals) => onChange({ usual_meals })}
                />
                <InputError message={errors.usual_meals} />
            </div>

            <div className="grid gap-2">
                <Label>What did you eat yesterday? (optional, up to 8)</Label>
                <p className="text-xs text-muted-foreground">
                    Pick foods from our database or type what you ate.
                </p>

                {!picker ? (
                    <div className="space-y-2">
                        <Skeleton className="h-20 w-full" />
                    </div>
                ) : (
                    <div className="space-y-3">
                        {value.diary.map((entry, index) => (
                            <div
                                key={index}
                                className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-[9rem_1fr_auto] sm:items-start"
                            >
                                <Select
                                    value={entry.meal}
                                    onValueChange={(meal) =>
                                        updateEntry(index, { meal })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {USUAL_MEALS.map((meal) => (
                                            <SelectItem
                                                key={meal.value}
                                                value={meal.value}
                                            >
                                                {meal.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <div className="grid gap-2">
                                    <FoodSearchField
                                        entry={entry}
                                        foods={allFoods}
                                        onChange={(patch) =>
                                            updateEntry(index, patch)
                                        }
                                    />
                                    {!entry.food_id && (
                                        <Input
                                            value={entry.free_text ?? ''}
                                            onChange={(event) =>
                                                updateEntry(index, {
                                                    free_text:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder="…or type what you ate"
                                        />
                                    )}
                                    <Input
                                        value={entry.portion_note ?? ''}
                                        onChange={(event) =>
                                            updateEntry(index, {
                                                portion_note:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="Portion (optional), e.g. 1 injera"
                                    />
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeEntry(index)}
                                    aria-label={`Remove entry ${index + 1}`}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}

                        {value.diary.length < MAX_DIARY_ENTRIES && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addEntry}
                            >
                                <Plus className="size-4" />
                                Add a meal or food
                            </Button>
                        )}
                        <InputError message={errors.diary} />
                    </div>
                )}
            </div>

            <div className="grid gap-2">
                <Label>What would you like to improve?</Label>
                <ChipMulti
                    options={IMPROVEMENTS}
                    values={value.improvements}
                    onChange={(improvements) => onChange({ improvements })}
                />
                <InputError message={errors.improvements} />
            </div>
        </div>
    );
}
