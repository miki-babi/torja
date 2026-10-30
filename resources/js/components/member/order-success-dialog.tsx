import { CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/lib/i18n';

export function OrderSuccessDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader className="items-center text-center">
                    <CheckCircle2 className="size-12 text-[#AFFA01]" />
                    <DialogTitle>{t('shop.order_placed')}</DialogTitle>
                    <DialogDescription className="text-[#8E8E93]">
                        {t('shop.cod_hint')}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex-col gap-2 sm:flex-col">
                    <DialogClose asChild>
                        <Button className="w-full rounded-full bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] text-sm font-extrabold text-black hover:opacity-90">
                            {t('common.ok')}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
