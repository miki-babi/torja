import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { ProgressBar } from '@/components/plan/progress-bar';

/**
 * Shared chrome for one wizard step: progress, title/description, and
 * back/next navigation.
 */
export function WizardShell({
    step,
    totalSteps,
    title,
    description,
    onBack,
    onNext,
    nextLabel = 'Continue',
    processing = false,
    children,
}: {
    step: number;
    totalSteps: number;
    title: string;
    description?: string;
    onBack?: () => void;
    onNext?: () => void;
    nextLabel?: string;
    processing?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-4">
            <ProgressBar step={step} total={totalSteps} />
            <p className="text-sm text-muted-foreground">
                Step {step} of {totalSteps} — you can edit any answer later.
            </p>

            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </CardHeader>
                <CardContent>{children}</CardContent>
                <CardFooter className="flex justify-between gap-4">
                    {onBack ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onBack}
                            disabled={processing}
                        >
                            Back
                        </Button>
                    ) : (
                        <span />
                    )}
                    {onNext && (
                        <Button
                            type="button"
                            onClick={onNext}
                            disabled={processing}
                            className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                        >
                            {processing && <Spinner />}
                            {nextLabel}
                        </Button>
                    )}
                </CardFooter>
            </Card>
        </div>
    );
}
