import type { Option } from '@/lib/plan-options';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

/**
 * Multi-select chip list. Pass `max` to cap selections.
 */
export function ChipMulti({
    options,
    values,
    onChange,
    max,
}: {
    options: Option[];
    values: string[];
    onChange: (values: string[]) => void;
    max?: number;
}) {
    function toggle(value: string) {
        if (values.includes(value)) {
            onChange(values.filter((item) => item !== value));
            return;
        }
        if (max !== undefined && values.length >= max) {
            return;
        }
        onChange([...values, value]);
    }

    return (
        <div className="flex flex-wrap gap-2">
            {options.map((option) => {
                const selected = values.includes(option.value);
                const disabled =
                    !selected && max !== undefined && values.length >= max;

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={selected}
                        disabled={disabled}
                        onClick={() => toggle(option.value)}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors',
                            selected
                                ? 'border-foreground bg-foreground text-background'
                                : 'border-border hover:border-foreground/40',
                            disabled && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        {selected && <Check className="size-3.5" />}
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
