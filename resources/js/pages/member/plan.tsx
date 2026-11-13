import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck } from 'lucide-react';
import { useState } from 'react';
import { MobileShell } from '@/components/member/mobile-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { checkout } from '@/routes/member/plans';

type Props = {
    plan: {
        id: number;
        name: string;
        description: string | null;
        kind_label: string;
        price_etb: number;
        duration_days: number;
    };
    trainer: {
        name: string;
        slug: string;
    };
};

export default function MemberPlan({ plan, trainer }: Props) {
    const [processing, setProcessing] = useState(false);

    const pay = () => {
        setProcessing(true);
        router.post(checkout(plan.id).url, undefined, {
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <MobileShell hideNav>
            <Head title={plan.name} />
            <div className="grid gap-5">
                <Link
                    href={`/t/${trainer.slug}`}
                    className="flex w-fit items-center gap-1 text-sm text-muted-foreground"
                >
                    <ArrowLeft className="size-4" />
                    {trainer.name}
                </Link>

                <Card>
                    <CardContent className="grid gap-4 py-6">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <h1 className="text-lg font-bold">
                                    {plan.name}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {plan.kind_label} · {plan.duration_days}{' '}
                                    days
                                </p>
                            </div>
                            <Badge variant="secondary" className="text-base">
                                {plan.price_etb} ETB
                            </Badge>
                        </div>

                        {plan.description && (
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {plan.description}
                            </p>
                        )}

                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <BadgeCheck className="size-3.5" />
                            Coached by {trainer.name}
                        </p>

                        <Button
                            size="lg"
                            onClick={pay}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Pay {plan.price_etb} ETB
                        </Button>

                        <p className="text-center text-xs text-muted-foreground">
                            Telebirr, M-Pesa or CBE Birr — powered by Jami.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </MobileShell>
    );
}
