import {
    Check,
    Dumbbell,
    PersonStanding,
    Search,
    Weight,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const FEATURED: { value: string; label: string; Icon: LucideIcon }[] = [
    { value: 'body weight', label: 'Body weight', Icon: PersonStanding },
    { value: 'barbell', label: 'Barbell', Icon: Weight },
    { value: 'dumbbell', label: 'Dumbbell', Icon: Dumbbell },
];

/**
 * Equipment filter for the explore screen: featured tiles plus an "Other"
 * tile that opens a searchable dialog over the remaining equipment values.
 * Multiple selections are allowed; an empty list means "any equipment".
 */
export function EquipmentPicker({
    equipment,
    value,
    onChange,
}: {
    equipment: string[];
    value: string[];
    onChange: (value: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const featuredValues = FEATURED.map((f) => f.value);
    const others = equipment.filter(
        (e) =>
            !featuredValues.includes(e) &&
            e.includes(query.trim().toLowerCase()),
    );
    const otherSelected = value.some((v) => !featuredValues.includes(v));

    function toggle(equipmentValue: string) {
        onChange(
            value.includes(equipmentValue)
                ? value.filter((v) => v !== equipmentValue)
                : [...value, equipmentValue],
        );
    }

    return (
        <>
            <div className="grid grid-cols-4 gap-2">
                {FEATURED.map(({ value: equipmentValue, label, Icon }) => (
                    <button
                        key={equipmentValue}
                        type="button"
                        onClick={() => toggle(equipmentValue)}
                        className={cn(
                            'flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border px-1',
                            value.includes(equipmentValue)
                                ? 'border-lime-500 bg-lime-300/40 text-lime-900 dark:text-lime-100'
                                : 'border-input text-muted-foreground',
                        )}
                    >
                        <Icon className="size-5 shrink-0" />
                        <span className="text-[10px] leading-none">
                            {label}
                        </span>
                    </button>
                ))}
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className={cn(
                        'flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border px-1',
                        otherSelected
                            ? 'border-lime-500 bg-lime-300/40 text-lime-900 dark:text-lime-100'
                            : 'border-input text-muted-foreground',
                    )}
                >
                    <Search className="size-5 shrink-0" />
                    <span className="text-[10px] leading-none">Other</span>
                </button>
            </div>

            {value.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {value.map((equipmentValue) => (
                        <button
                            key={equipmentValue}
                            type="button"
                            onClick={() => toggle(equipmentValue)}
                            aria-label={`Remove ${equipmentValue} filter`}
                            className="flex items-center gap-1 rounded-full border border-lime-500 bg-lime-300/40 px-2.5 py-1 text-xs text-lime-900 capitalize dark:text-lime-100"
                        >
                            {equipmentValue}
                            <X className="size-3" />
                        </button>
                    ))}
                </div>
            )}

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (!next) {
                        setQuery('');
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Other equipment</DialogTitle>
                        <DialogDescription>
                            Search the remaining equipment types. Select as many
                            as you like.
                        </DialogDescription>
                    </DialogHeader>
                    <Input
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search equipment…"
                    />
                    <div className="max-h-64 divide-y overflow-y-auto rounded-md border">
                        {others.map((e) => (
                            <button
                                key={e}
                                type="button"
                                onClick={() => toggle(e)}
                                className="flex w-full items-center justify-between px-3 py-2 text-left text-sm capitalize hover:bg-accent"
                            >
                                {e}
                                {value.includes(e) && (
                                    <Check className="size-4" />
                                )}
                            </button>
                        ))}
                        {others.length === 0 && (
                            <p className="px-3 py-4 text-center text-sm text-muted-foreground">
                                No equipment matches.
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
