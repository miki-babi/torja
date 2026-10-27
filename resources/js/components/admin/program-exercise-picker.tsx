import { Check, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EquipmentPicker } from '@/components/member/equipment-picker';
import {
    type ExerciseSummaries,
    type ExerciseSummary,
} from '@/components/member/exercise-data';
import { searchExercises } from '@/components/member/exercise-search';
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

type BodyPartOption = { id: string; label: string; count: number };

/**
 * Shared exercise picker for the program builder: text search plus body
 * part, equipment and level filters, with multi-select. Already-added
 * exercises are shown disabled via `exclude`.
 */
export function ProgramExercisePicker({
    open,
    onOpenChange,
    exclude,
    onAdd,
    bodyParts,
    equipment,
    levels,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    exclude: string[];
    onAdd: (slugs: string[], summaries: ExerciseSummaries) => void;
    bodyParts: BodyPartOption[];
    equipment: string[];
    levels: string[];
}) {
    const [query, setQuery] = useState('');
    const [bodyPart, setBodyPart] = useState('');
    const [equipmentSel, setEquipmentSel] = useState<string[]>([]);
    const [level, setLevel] = useState('');
    const [results, setResults] = useState<ExerciseSummary[]>([]);
    const [selected, setSelected] = useState<Record<string, ExerciseSummary>>(
        {},
    );
    const [loading, setLoading] = useState(false);
    const firstSearch = useRef(true);

    useEffect(() => {
        if (!open) {
            return;
        }
        if (firstSearch.current) {
            firstSearch.current = false;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            setLoading(true);
            searchExercises({
                q: query,
                body_part: bodyPart === '' ? [] : [bodyPart],
                equipment: equipmentSel,
                level,
                limit: 30,
            })
                .then((data) => {
                    if (!cancelled) {
                        setResults(data.exercises);
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
    }, [open, query, bodyPart, equipmentSel, level]);

    function toggle(exercise: ExerciseSummary) {
        if (exclude.includes(exercise.slug)) {
            return;
        }
        setSelected((map) => {
            const next = { ...map };
            if (next[exercise.slug] !== undefined) {
                delete next[exercise.slug];
            } else {
                next[exercise.slug] = exercise;
            }
            return next;
        });
    }

    function addSelected() {
        const summaries = selected;
        onAdd(Object.keys(summaries), summaries);
        setSelected({});
        setQuery('');
        onOpenChange(false);
    }

    const selectedCount = Object.keys(selected).length;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[85vh] flex-col gap-3 sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add exercises</DialogTitle>
                    <DialogDescription>
                        Search and filter, tap to select, then add them to the
                        workout.
                    </DialogDescription>
                </DialogHeader>

                <div className="relative">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search exercises"
                        className="pl-9"
                        autoFocus
                    />
                </div>

                <div className="flex flex-wrap gap-1.5">
                    {bodyParts.map((part) => (
                        <button
                            key={part.id}
                            type="button"
                            onClick={() =>
                                setBodyPart(bodyPart === part.id ? '' : part.id)
                            }
                            className={cn(
                                'rounded-full border px-2.5 py-1 text-xs',
                                bodyPart === part.id
                                    ? 'border-lime-500 bg-lime-300/40 font-medium'
                                    : 'border-input text-muted-foreground',
                            )}
                        >
                            {part.label}
                        </button>
                    ))}
                </div>

                <EquipmentPicker
                    equipment={equipment}
                    value={equipmentSel}
                    onChange={setEquipmentSel}
                />

                <div className="flex flex-wrap gap-1.5">
                    {['', ...levels].map((l) => (
                        <button
                            key={l || 'any'}
                            type="button"
                            onClick={() => setLevel(l)}
                            className={cn(
                                'rounded-full border px-2.5 py-1 text-xs capitalize',
                                level === l
                                    ? 'border-lime-500 bg-lime-300/40 font-medium'
                                    : 'border-input text-muted-foreground',
                            )}
                        >
                            {l === '' ? 'Any level' : l}
                        </button>
                    ))}
                </div>

                <div className="-mx-1 min-h-40 flex-1 divide-y overflow-y-auto px-1">
                    {results.length === 0 && !loading && (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No exercises match those filters.
                        </p>
                    )}
                    {results.map((ex) => {
                        const added = exclude.includes(ex.slug);
                        const picked = selected[ex.slug] !== undefined;
                        return (
                            <button
                                key={ex.slug}
                                type="button"
                                disabled={added}
                                onClick={() => toggle(ex)}
                                className={cn(
                                    'flex w-full items-center gap-3 px-2 py-2 text-left disabled:opacity-40',
                                    picked && 'bg-lime-300/30',
                                )}
                            >
                                <img
                                    src={ex.image_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-8 shrink-0 rounded-lg object-cover"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium capitalize">
                                        {ex.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {ex.detail}
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
                        {selectedCount === 1 ? 'exercise' : 'exercises'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
