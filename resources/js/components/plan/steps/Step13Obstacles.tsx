import InputError from '@/components/input-error';
import { ChipMulti } from '@/components/plan/chip-multi';
import type { StepProps } from '@/components/plan/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    OBSTACLE_OPTIONS,
    SUPPORT_OPTIONS,
    type ObstaclesAnswers,
} from '@/lib/plan-options';

export default function Step13Obstacles({
    value,
    onChange,
    errors,
}: StepProps<ObstaclesAnswers>) {
    const hasOther = value.obstacles.includes('other');

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>What usually gets in the way?</Label>
                <ChipMulti
                    options={OBSTACLE_OPTIONS}
                    values={value.obstacles}
                    onChange={(obstacles) => onChange({ obstacles })}
                />
                <InputError message={errors.obstacles} />
            </div>

            {hasOther && (
                <div className="grid gap-2">
                    <Label htmlFor="obstacle_other">Tell us about it</Label>
                    <Input
                        id="obstacle_other"
                        value={value.obstacle_other}
                        onChange={(event) =>
                            onChange({ obstacle_other: event.target.value })
                        }
                        placeholder="e.g. Frequent travel for work"
                    />
                    <InputError message={errors.obstacle_other} />
                </div>
            )}

            <div className="grid gap-2">
                <Label>What kind of support would help?</Label>
                <ChipMulti
                    options={SUPPORT_OPTIONS}
                    values={value.support}
                    onChange={(support) => onChange({ support })}
                />
                <InputError message={errors.support} />
            </div>

            <p className="text-sm text-muted-foreground">
                Almost done — continue to review your answers and see your plan
                preview.
            </p>
        </div>
    );
}
