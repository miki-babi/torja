import { Plus, X } from 'lucide-react';
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
import {
    IMPROVEMENTS,
    USUAL_MEALS,
    type HabitsAnswers,
} from '@/lib/plan-options';

const MAX_DIARY_ENTRIES = 8;

export default function Step09Habits({
    value,
    onChange,
    errors,
}: StepProps<HabitsAnswers>) {
    function updateEntry(
        index: number,
        patch: Partial<HabitsAnswers['diary'][number]>,
    ) {
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
                    Type what you ate for each meal.
                </p>

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
                                <Input
                                    value={entry.free_text ?? ''}
                                    onChange={(event) =>
                                        updateEntry(index, {
                                            free_text: event.target.value,
                                        })
                                    }
                                    placeholder="What you ate"
                                />
                                <Input
                                    value={entry.portion_note ?? ''}
                                    onChange={(event) =>
                                        updateEntry(index, {
                                            portion_note: event.target.value,
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
