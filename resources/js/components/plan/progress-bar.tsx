import { cn } from '@/lib/utils';

/**
 * Segmented wizard progress bar (no progress component exists in ui/).
 */
export function ProgressBar({ step, total }: { step: number; total: number }) {
    return (
        <div
            className="flex gap-1.5"
            role="progressbar"
            aria-valuenow={step}
            aria-valuemin={1}
            aria-valuemax={total}
        >
            {Array.from({ length: total }, (_, index) => (
                <span
                    key={index}
                    className={cn(
                        'h-1.5 flex-1 rounded-full',
                        index < step ? 'bg-lime-400' : 'bg-muted',
                    )}
                />
            ))}
        </div>
    );
}
