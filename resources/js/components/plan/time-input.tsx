import { Input } from '@/components/ui/input';
import type { ComponentProps } from 'react';

/**
 * HH:MM time field matching the Input component styling.
 */
export function TimeInput(props: ComponentProps<typeof Input>) {
    return <Input type="time" {...props} />;
}
