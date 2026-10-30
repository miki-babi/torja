import { CheckboxChoice } from '@/components/calculator/checkbox-choice';
import { Label } from '@/components/ui/label';
import type { Sex } from '@/lib/body-metrics';

export function SexToggle({
    value,
    onChange,
}: {
    value: Sex;
    onChange: (value: Sex) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>Sex</Label>
            <CheckboxChoice
                value={value}
                onChange={onChange}
                options={[
                    { value: 'male', label: 'Male' },
                    { value: 'female', label: 'Female' },
                ]}
            />
        </div>
    );
}
