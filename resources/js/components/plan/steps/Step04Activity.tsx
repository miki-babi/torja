import InputError from '@/components/input-error';
import { ChoiceCards } from '@/components/plan/choice-card';
import { ChipMulti } from '@/components/plan/chip-multi';
import type { StepProps } from '@/components/plan/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    DAILY_ACTIVITY,
    EXERCISE_DAYS,
    EXERCISE_TYPES,
    type ActivityAnswers,
} from '@/lib/plan-options';

export default function Step04Activity({
    value,
    onChange,
    errors,
}: StepProps<ActivityAnswers>) {
    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>How active is a typical day?</Label>
                <ChoiceCards
                    options={DAILY_ACTIVITY}
                    value={value.daily_activity}
                    onChange={(daily_activity) => onChange({ daily_activity })}
                />
                <InputError message={errors.daily_activity} />
            </div>

            <div className="grid gap-2">
                <Label>How many days a week do you exercise?</Label>
                <ChoiceCards
                    options={EXERCISE_DAYS}
                    value={value.exercise_days}
                    onChange={(exercise_days) => onChange({ exercise_days })}
                    columns={2}
                />
                <InputError message={errors.exercise_days} />
            </div>

            <div className="grid gap-2">
                <Label>What kind of exercise? (optional)</Label>
                <ChipMulti
                    options={EXERCISE_TYPES}
                    values={value.exercise_types}
                    onChange={(exercise_types) => onChange({ exercise_types })}
                />
                <InputError message={errors.exercise_types} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="avg_daily_steps">
                    Average daily steps (optional)
                </Label>
                <Input
                    id="avg_daily_steps"
                    type="number"
                    inputMode="numeric"
                    min="0"
                    value={value.avg_daily_steps}
                    onChange={(event) =>
                        onChange({ avg_daily_steps: event.target.value })
                    }
                    placeholder="e.g. 6000"
                />
                <InputError message={errors.avg_daily_steps} />
            </div>
        </div>
    );
}
