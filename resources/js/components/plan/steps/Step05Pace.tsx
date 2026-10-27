import InputError from '@/components/input-error';
import { ChoiceCards } from '@/components/plan/choice-card';
import type { StepProps } from '@/components/plan/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PACES, type PaceAnswers } from '@/lib/plan-options';

export default function Step05Pace({
    value,
    onChange,
    errors,
}: StepProps<PaceAnswers>) {
    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label>How fast do you want to go?</Label>
                <ChoiceCards
                    options={PACES}
                    value={value.pace}
                    onChange={(pace) => onChange({ pace })}
                />
                <InputError message={errors.pace} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="target_date">Target date (optional)</Label>
                <Input
                    id="target_date"
                    type="date"
                    value={value.target_date}
                    onChange={(event) =>
                        onChange({ target_date: event.target.value })
                    }
                />
                <p className="text-xs text-muted-foreground">
                    If you have a date in mind — a wedding, competition, or
                    check-up — we'll check whether it's realistic.
                </p>
                <InputError message={errors.target_date} />
            </div>
        </div>
    );
}
