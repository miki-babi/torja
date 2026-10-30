import { CheckboxChoice } from '@/components/calculator/checkbox-choice';
import { Label } from '@/components/ui/label';
import type { UnitSystem } from '@/lib/body-metrics';

export function UnitToggle({
    value,
    onChange,
}: {
    value: UnitSystem;
    onChange: (next: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>Units</Label>
            <CheckboxChoice
                value={value}
                onChange={onChange}
                options={[
                    { value: 'metric', label: 'Metric' },
                    { value: 'imperial', label: 'Imperial' },
                ]}
            />
        </div>
    );
}
