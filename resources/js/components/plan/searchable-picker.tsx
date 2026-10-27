import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Check, X } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface PickerItem {
    id: number;
    label: string;
    sublabel?: string;
}

/**
 * Search input + scrollable toggle list + selected chips. Used by the food
 * pickers (liked foods, dislikes) and ingredient pickers.
 */
export function SearchablePicker({
    items,
    selectedIds,
    onToggle,
    placeholder = 'Search…',
    emptyLabel = 'No matches.',
}: {
    items: PickerItem[];
    selectedIds: number[];
    onToggle: (id: number) => void;
    placeholder?: string;
    emptyLabel?: string;
}) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return items;
        }
        return items.filter(
            (item) =>
                item.label.toLowerCase().includes(needle) ||
                item.sublabel?.toLowerCase().includes(needle),
        );
    }, [items, query]);

    const selectedItems = items.filter((item) => selectedIds.includes(item.id));

    return (
        <div className="grid gap-2">
            <Input
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={placeholder}
            />

            {selectedItems.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {selectedItems.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => onToggle(item.id)}
                            className="inline-flex items-center gap-1 rounded-full border border-foreground bg-foreground px-2.5 py-1 text-xs text-background"
                        >
                            {item.label}
                            <X className="size-3" />
                        </button>
                    ))}
                </div>
            )}

            <div className="max-h-56 overflow-y-auto rounded-md border border-border">
                {filtered.length === 0 && (
                    <p className="p-3 text-sm text-muted-foreground">
                        {emptyLabel}
                    </p>
                )}
                {filtered.map((item) => {
                    const selected = selectedIds.includes(item.id);
                    return (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => onToggle(item.id)}
                            className={cn(
                                'flex w-full items-center gap-2 border-b border-border/60 px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/60',
                                selected && 'bg-muted/40',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-4 shrink-0 items-center justify-center rounded-sm border border-border',
                                    selected &&
                                        'border-foreground bg-foreground text-background',
                                )}
                            >
                                {selected && <Check className="size-3" />}
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate">
                                    {item.label}
                                </span>
                                {item.sublabel && (
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {item.sublabel}
                                    </span>
                                )}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
