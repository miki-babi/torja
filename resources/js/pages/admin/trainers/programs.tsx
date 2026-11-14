import { Head, Link, router } from '@inertiajs/react';
import { Dumbbell, ExternalLink, UtensilsCrossed } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import {
    index as trainersIndex,
    programs,
    show,
} from '@/routes/admin/trainers';

type Plan = {
    id: number;
    name: string;
    price_etb: number;
    is_free: boolean;
    shared: boolean;
    share_url: string | null;
    created_at: string;
};

type Props = {
    trainer: { id: number; name: string };
    filters: { free: boolean };
    programs: Plan[];
    mealPrograms: Plan[];
};

function PlanRow({ plan }: { plan: Plan }) {
    return (
        <div className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
            <span className="flex min-w-0 items-center gap-2">
                <span className="truncate">{plan.name}</span>
                {plan.shared && (
                    <Badge variant="outline" className="shrink-0">
                        Shared
                    </Badge>
                )}
            </span>
            <span className="flex shrink-0 items-center gap-2">
                <span className="text-xs text-muted-foreground">
                    {plan.created_at}
                </span>
                <Badge variant={plan.is_free ? 'secondary' : 'default'}>
                    {plan.is_free
                        ? 'Free'
                        : `${plan.price_etb.toLocaleString()} ETB`}
                </Badge>
                {plan.share_url !== null && (
                    <Button asChild size="sm" variant="ghost">
                        <a
                            href={plan.share_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink className="size-4" />
                        </a>
                    </Button>
                )}
            </span>
        </div>
    );
}

export default function AdminTrainerPrograms({
    trainer,
    filters,
    programs: exercisePrograms,
    mealPrograms,
}: Props) {
    function toggleFree(free: boolean) {
        router.get(
            programs(trainer.id).url,
            free ? { free: 1 } : {},
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title={`Programs — ${trainer.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={`${trainer.name} — programs`}
                    description="Every workout and meal program this coach has built."
                    actions={
                        <div className="flex items-center gap-1">
                            <Button
                                size="sm"
                                variant={
                                    filters.free ? 'outline' : 'secondary'
                                }
                                onClick={() => toggleFree(false)}
                            >
                                All
                            </Button>
                            <Button
                                size="sm"
                                variant={
                                    filters.free ? 'secondary' : 'outline'
                                }
                                onClick={() => toggleFree(true)}
                            >
                                Free only
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Dumbbell className="size-4 text-muted-foreground" />
                            Workout programs ({exercisePrograms.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-1.5">
                        {exercisePrograms.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No workout programs.
                            </p>
                        ) : (
                            exercisePrograms.map((plan) => (
                                <PlanRow key={plan.id} plan={plan} />
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <UtensilsCrossed className="size-4 text-muted-foreground" />
                            Meal programs ({mealPrograms.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-1.5">
                        {mealPrograms.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No meal programs.
                            </p>
                        ) : (
                            mealPrograms.map((plan) => (
                                <PlanRow key={`m-${plan.id}`} plan={plan} />
                            ))
                        )}
                    </CardContent>
                </Card>

                <Button asChild variant="ghost" size="sm" className="self-start">
                    <Link href={show(trainer.id).url}>
                        Back to trainer
                    </Link>
                </Button>
            </div>
        </>
    );
}

AdminTrainerPrograms.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Trainers', href: trainersIndex() },
    ],
};
