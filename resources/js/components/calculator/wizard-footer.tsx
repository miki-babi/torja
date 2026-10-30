import { Button } from '@/components/ui/button';

export function WizardFooter({
    stepIndex,
    isPenultimate,
    isLast,
    canContinue,
    onBack,
    onNext,
    onReset,
}: {
    stepIndex: number;
    isPenultimate: boolean;
    isLast: boolean;
    canContinue: boolean;
    onBack: () => void;
    onNext: () => void;
    onReset: () => void;
}) {
    return (
        <div className="flex items-center justify-between">
            <Button variant="ghost" onClick={onBack} disabled={stepIndex === 0}>
                Back
            </Button>
            {isLast ? (
                <Button onClick={onReset}>Start over</Button>
            ) : (
                <Button onClick={onNext} disabled={!canContinue}>
                    {isPenultimate ? 'See results' : 'Next'}
                </Button>
            )}
        </div>
    );
}
