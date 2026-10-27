import InputError from '@/components/input-error';
import { ChoiceCards } from '@/components/plan/choice-card';
import { TimeInput } from '@/components/plan/time-input';
import type { StepProps } from '@/components/plan/types';
import { Label } from '@/components/ui/label';
import {
    EATING_LOCATIONS,
    MEALS_PER_DAY,
    PORTABLE_MEALS,
    WEEKDAY_SCHEDULES,
    type ScheduleAnswers,
} from '@/lib/plan-options';

export default function Step10Schedule({
    value,
    onChange,
    errors,
}: StepProps<ScheduleAnswers>) {
    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>How many meals a day works for you?</Label>
                <ChoiceCards
                    options={MEALS_PER_DAY}
                    value={value.meals_per_day}
                    onChange={(meals_per_day) => onChange({ meals_per_day })}
                />
                <InputError message={errors.meals_per_day} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="wake_time">
                        Usual wake time (optional)
                    </Label>
                    <TimeInput
                        id="wake_time"
                        value={value.wake_time}
                        onChange={(event) =>
                            onChange({ wake_time: event.target.value })
                        }
                    />
                    <InputError message={errors.wake_time} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="sleep_time">
                        Usual sleep time (optional)
                    </Label>
                    <TimeInput
                        id="sleep_time"
                        value={value.sleep_time}
                        onChange={(event) =>
                            onChange({ sleep_time: event.target.value })
                        }
                    />
                    <InputError message={errors.sleep_time} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label>What do your weekdays look like?</Label>
                <ChoiceCards
                    options={WEEKDAY_SCHEDULES}
                    value={value.weekday_schedule}
                    onChange={(weekday_schedule) =>
                        onChange({ weekday_schedule })
                    }
                    columns={2}
                />
                <InputError message={errors.weekday_schedule} />
            </div>

            <div className="grid gap-2">
                <Label>Where do you eat most meals?</Label>
                <ChoiceCards
                    options={EATING_LOCATIONS}
                    value={value.eating_location}
                    onChange={(eating_location) =>
                        onChange({ eating_location })
                    }
                    columns={2}
                />
                <InputError message={errors.eating_location} />
            </div>

            <div className="grid gap-2">
                <Label>Can you carry meals with you?</Label>
                <ChoiceCards
                    options={PORTABLE_MEALS}
                    value={value.portable_meals}
                    onChange={(portable_meals) => onChange({ portable_meals })}
                    columns={2}
                />
                <InputError message={errors.portable_meals} />
            </div>
        </div>
    );
}
