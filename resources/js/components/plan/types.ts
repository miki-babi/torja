import type { PickerProps } from '@/lib/plan-options';

/**
 * Props every wizard step component receives: the section's answer object,
 * a patch callback, current validation errors, and the deferred picker data.
 */
export type StepProps<T> = {
    value: T;
    onChange: (patch: Partial<T>) => void;
    errors: Record<string, string>;
    picker?: PickerProps;
};
