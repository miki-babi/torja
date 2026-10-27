import { Check, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FoodSearchResult } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { search } from '@/routes/member/foods';

export type FoodSummaries = Record<number, FoodSearchResult>;

/**
 * Food picker for the meal program builder: debounced search against the
 * member food search endpoint with multi-select. Already-added foods are
 * shown disabled via `exclude`.
 */
export function ProgramFoodPicker({
    open,
    onOpenChange,
    exclude,
    onAdd,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    exclude: number[];
    onAdd: (foods: FoodSearchResult[]) => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<FoodSearchResult[]>([]);
    const [selected, setSelected] = useState<Record<number, FoodSearchResult>>(
        {},
    );
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            setLoading(true);
            fetch(`${search().url}?q=${encodeURIComponent(query.trim())}`)
                .then((response) =>
                    response.ok ? response.json() : { foods: [] },
                )
                .then((data) => {
                    if (!cancelled) {
                        setResults(data.foods ?? []);
                    }
                })
                .catch(() => {})
                .finally(() => {
                    if (!cancelled) {
                        setLoading(false);
                    }
                });
        }, 250);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [open, query]);

    function toggle(food: FoodSearchResult) {
        if (exclude.includes(food.id)) {
            return;
        }
        setSelected((map) => {
            const next = { ...map };
            if (next[food.id] !== undefined) {
                delete next[food.id];
            } else {
                next[food.id] = food;
            }
            return next;
        });
    }

    function addSelected() {
        onAdd(Object.values(selected));
        setSelected({});
        setQuery('');
        onOpenChange(false);
    }

    const selectedCount = Object.keys(selected).length;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[85vh] flex-col gap-3 sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add foods</DialogTitle>
                    <DialogDescription>
                        Search the food database, tap to select, then add them
                        to the meal.
                    </DialogDescription>
                </DialogHeader>

                <div className="relative">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search foods"
                        className="pl-9"
                        autoFocus
                    />
                </div>

                <div className="-mx-1 min-h-40 flex-1 divide-y overflow-y-auto px-1">
                    {results.length === 0 && !loading && (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No foods match that search.
                        </p>
                    )}
                    {results.map((food) => {
                        const added = exclude.includes(food.id);
                        const picked = selected[food.id] !== undefined;
                        return (
                            <button
                                key={food.id}
                                type="button"
                                disabled={added}
                                onClick={() => toggle(food)}
                                className={cn(
                                    'flex w-full items-center gap-3 px-2 py-2 text-left disabled:opacity-40',
                                    picked && 'bg-lime-300/30',
                                )}
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">
                                        {food.name_en}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {food.kcal_per_100g ?? '—'} kcal/100g
                                        {food.protein_g !== null &&
                                            ` · ${food.protein_g}g protein`}
                                    </p>
                                </div>
                                {added ? (
                                    <span className="text-xs text-muted-foreground">
                                        Added
                                    </span>
                                ) : (
                                    picked && (
                                        <Check className="size-4 shrink-0 text-lime-700 dark:text-lime-400" />
                                    )
                                )}
                            </button>
                        );
                    })}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        disabled={selectedCount === 0}
                        onClick={addSelected}
                        className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                    >
                        Add {selectedCount > 0 ? `${selectedCount} ` : ''}
                        {selectedCount === 1 ? 'food' : 'foods'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
