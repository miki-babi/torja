import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import type { StepProps } from '@/components/plan/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LIKED_FOOD_TAGS, type LikedFoodsAnswers } from '@/lib/plan-options';
import { cn } from '@/lib/utils';

export default function Step07LikedFoods({
    value,
    onChange,
    errors,
}: StepProps<LikedFoodsAnswers>) {
    const [query, setQuery] = useState('');

    function addFood() {
        const name = query.trim();
        if (
            name === '' ||
            value.liked_foods.some((item) => item.name === name)
        ) {
            return;
        }
        onChange({
            liked_foods: [...value.liked_foods, { name, tags: ['enjoy'] }],
        });
        setQuery('');
    }

    function removeFood(name: string) {
        onChange({
            liked_foods: value.liked_foods.filter((item) => item.name !== name),
        });
    }

    function toggleTag(name: string, tag: string) {
        onChange({
            liked_foods: value.liked_foods.map((item) => {
                if (item.name !== name) {
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

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="liked-food-input">
                    Type the foods you enjoy
                </Label>
                <div className="flex gap-2">
                    <Input
                        id="liked-food-input"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                addFood();
                            }
                        }}
                        placeholder="e.g. Shiro, tibs, kitfo…"
                        autoComplete="off"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={addFood}
                        disabled={query.trim() === ''}
                        aria-label="Add food"
                    >
                        <Plus className="size-4" />
                    </Button>
                </div>
            </div>

            {value.liked_foods.length > 0 && (
                <div className="grid gap-2">
                    <Label>
                        Your picks ({value.liked_foods.length}) — tag how you
                        feel about each
                    </Label>
                    <div className="space-y-2 rounded-md border border-border p-3">
                        {value.liked_foods.map((item) => (
                            <div
                                key={item.name}
                                className="flex flex-wrap items-center gap-2"
                            >
                                <button
                                    type="button"
                                    onClick={() => removeFood(item.name)}
                                    className="inline-flex items-center gap-1 rounded-full border border-foreground bg-foreground px-2.5 py-1 text-xs text-background"
                                >
                                    {item.name}
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
                                                toggleTag(item.name, tag.value)
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

            <InputError message={errors.liked_foods} />
        </div>
    );
}
