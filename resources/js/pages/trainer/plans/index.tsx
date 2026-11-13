import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Tag, Trash2 } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/trainer';
import {
    create,
    destroy,
    edit,
    index,
    toggle,
} from '@/routes/trainer/plans';

type Plan = {
    id: number;
    name: string;
    kind: string;
    kind_label: string;
    price_etb: number;
    duration_days: number;
    is_active: boolean;
    client_plans_count: number;
};

type Props = {
    plans: Plan[];
};

export default function TrainerPlans({ plans }: Props) {
    return (
        <>
            <Head title="Coaching plans" />
            <div className="grid gap-6">
                <AdminPageHeader
                    title="Coaching plans"
                    description="Priced, time-boxed coaching offers shown on your public page."
                />

                <div className="flex">
                    <Button asChild className="ml-auto">
                        <Link href={create().url}>
                            <Plus />
                            New plan
                        </Link>
                    </Button>
                </div>

                {plans.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-12 text-center text-sm text-muted-foreground">
                            <Tag className="size-6" />
                            No coaching plans yet. Create one and it appears
                            on your public page.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3">
                        {plans.map((plan) => (
                            <Card key={plan.id}>
                                <CardHeader className="flex-row items-center justify-between gap-3 space-y-0">
                                    <div className="min-w-0">
                                        <CardTitle className="truncate text-base">
                                            {plan.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {plan.kind_label} ·{' '}
                                            {plan.duration_days} days ·{' '}
                                            {plan.client_plans_count}{' '}
                                            subscriber
                                            {plan.client_plans_count === 1
                                                ? ''
                                                : 's'}
                                        </CardDescription>
                                    </div>
                                    <Badge variant="secondary">
                                        {plan.price_etb} ETB
                                    </Badge>
                                </CardHeader>
                                <CardContent className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            plan.is_active
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        {plan.is_active ? 'Live' : 'Hidden'}
                                    </Badge>
                                    <div className="ml-auto flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    toggle(plan.id).url,
                                                )
                                            }
                                        >
                                            {plan.is_active
                                                ? 'Hide'
                                                : 'Publish'}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={edit(plan.id).url}>
                                                <Pencil />
                                                Edit
                                            </Link>
                                        </Button>
                                        {plan.client_plans_count === 0 && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.delete(
                                                        destroy(plan.id).url,
                                                    )
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

TrainerPlans.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Coaching plans', href: index() },
    ],
};
