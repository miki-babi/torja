/**
 * Props every wizard step component receives: the section's answer object,
 * a patch callback, and current validation errors.
 */
export type StepProps<T> = {
    value: T;
    onChange: (patch: Partial<T>) => void;
    errors: Record<string, string>;
};
