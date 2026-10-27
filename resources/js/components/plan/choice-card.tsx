import type { Option } from '@/lib/plan-options';
import { cn } from '@/lib/utils';

/**
 * Radio-style selectable cards for single-select wizard questions.
 */
export function ChoiceCards({
    options,
    value,
    onChange,
    columns = 1,
}: {
    options: Option[];
    value: string;
    onChange: (value: string) => void;
    columns?: 1 | 2;
}) {
    return (
        <div
            className={cn('grid gap-2', columns === 2 && 'sm:grid-cols-2')}
            role="radiogroup"
        >
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="radio"
                    aria-checked={value === option.value}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'rounded-lg border border-border p-3 text-left transition-colors hover:border-foreground/40',
                        value === option.value &&
                            'border-foreground bg-muted/50 ring-1 ring-foreground',
                    )}
                >
                    <span className="block text-sm font-medium">
                        {option.label}
                    </span>
                    {option.description && (
                        <span className="mt-0.5 block text-xs text-muted-foreground">
                            {option.description}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}
