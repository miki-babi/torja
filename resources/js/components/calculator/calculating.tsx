import { useEffect, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';

const CALC_MESSAGES = [
    'Checking your inputs…',
    'Applying the formulas…',
    'Finishing up…',
];

export function useCalculating() {
    const [isCalculating, setIsCalculating] = useState(false);
    const [messageIndex, setMessageIndex] = useState(0);

    useEffect(() => {
        if (!isCalculating) {
            return;
        }
        setMessageIndex(0);
        const messageTimer = setInterval(() => {
            setMessageIndex((i) => Math.min(i + 1, CALC_MESSAGES.length - 1));
        }, 700);
        const doneTimer = setTimeout(() => {
            setIsCalculating(false);
        }, 2200);
        return () => {
            clearInterval(messageTimer);
            clearTimeout(doneTimer);
        };
    }, [isCalculating]);

    return {
        isCalculating,
        messageIndex,
        start: () => setIsCalculating(true),
        stop: () => setIsCalculating(false),
    };
}

export function Calculating({ messageIndex }: { messageIndex: number }) {
    return (
        <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
            <Spinner className="size-8 text-muted-foreground" />
            <div>
                <p className="font-medium">Calculating your results</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {CALC_MESSAGES[messageIndex]}
                </p>
            </div>
        </div>
    );
}
