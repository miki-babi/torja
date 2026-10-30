import { Checkbox } from '@/components/ui/checkbox';

export function CheckboxChoice<T extends string>({
    value,
    onChange,
    options,
}: {
    value: T;
    onChange: (value: T) => void;
    options: { value: T; label: string }[];
}) {
    return (
        <div className="flex flex-wrap gap-x-5 gap-y-2">
            {options.map((option) => (
                <label
                    key={option.value}
                    className="flex cursor-pointer items-center gap-2 text-sm"
                >
                    <Checkbox
                        checked={value === option.value}
                        onCheckedChange={(checked) => {
                            if (checked) {
                                onChange(option.value);
                            }
                        }}
                    />
                    {option.label}
                </label>
            ))}
        </div>
    );
}
