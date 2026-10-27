import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmOptions = {
    title: string;
    description?: string;
    confirmLabel?: string;
    destructive?: boolean;
    onConfirm: () => void;
};

/**
 * Imperative-style confirmation dialog replacing `window.confirm`.
 *
 * ```tsx
 * const { confirm, confirmDialog } = useConfirm();
 * confirm({ title: 'Delete user?', description: '…', destructive: true, onConfirm: () => … });
 * // render {confirmDialog} once near the page root
 * ```
 */
export function useConfirm() {
    const [options, setOptions] = useState<ConfirmOptions | null>(null);

    const confirm = useCallback((opts: ConfirmOptions) => {
        setOptions(opts);
    }, []);

    const confirmDialog = (
        <Dialog
            open={options !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setOptions(null);
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{options?.title}</DialogTitle>
                    {options?.description && (
                        <DialogDescription>
                            {options.description}
                        </DialogDescription>
                    )}
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOptions(null)}>
                        Cancel
                    </Button>
                    <Button
                        variant={
                            options?.destructive ? 'destructive' : 'default'
                        }
                        onClick={() => {
                            options?.onConfirm();
                            setOptions(null);
                        }}
                    >
                        {options?.confirmLabel ?? 'Confirm'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    return { confirm, confirmDialog };
}
