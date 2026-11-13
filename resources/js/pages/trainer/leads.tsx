import { Head } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/trainer';
import { index } from '@/routes/trainer/leads';

type Stats = {
    new_this_week: number;
    total: number;
    converted: number;
    conversion_rate: number;
};

type Lead = {
    id: number;
    name: string;
    phone: string | null;
    program_name: string | null;
    answers: Record<string, string> | null;
    converted: boolean;
    created_at: string;
};

type Props = {
    stats: Stats;
    leads: Lead[];
};

const GOAL_LABELS: Record<string, string> = {
    lose_fat: 'Lose fat',
    build_muscle: 'Build muscle',
    get_fitter: 'Get fitter',
    sport: 'Sport performance',
    beginner: 'Beginner',
    intermediate: 'Intermediate',
    advanced: 'Advanced',
};

export default function TrainerLeads({ stats, leads }: Props) {
    return (
        <>
            <Head title="Leads" />
            <div className="grid gap-6">
                <AdminPageHeader
                    title="Leads"
                    description="Prospective clients who filled in your free-program questionnaire."
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>New this week</CardDescription>
                            <CardTitle className="text-xl">
                                {stats.new_this_week}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total leads</CardDescription>
                            <CardTitle className="text-xl">
                                {stats.total}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Registered</CardDescription>
                            <CardTitle className="text-xl">
                                {stats.converted}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Conversion</CardDescription>
                            <CardTitle className="text-xl">
                                {stats.conversion_rate}%
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Latest</CardTitle>
                        <CardDescription>
                            Newest leads first — registered accounts show as
                            converted.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {leads.length === 0 && (
                            <div className="flex flex-col items-center gap-2 py-10 text-center text-sm text-muted-foreground">
                                <UserPlus className="size-6" />
                                No leads yet. Share a free program link to
                                start collecting them.
                            </div>
                        )}
                        {leads.map((lead) => (
                            <div
                                key={lead.id}
                                className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {lead.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {lead.program_name ?? 'Program'} ·{' '}
                                        {lead.answers?.goal
                                            ? (GOAL_LABELS[
                                                  lead.answers.goal
                                              ] ?? lead.answers.goal)
                                            : '—'}
                                        {lead.phone ? ` · ${lead.phone}` : ''} ·{' '}
                                        {new Date(
                                            lead.created_at,
                                        ).toLocaleDateString()}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        lead.converted ? 'default' : 'outline'
                                    }
                                >
                                    {lead.converted ? 'Joined' : 'Lead'}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TrainerLeads.layout = {
    breadcrumbs: [
        { title: 'Coach', href: dashboard() },
        { title: 'Leads', href: index() },
    ],
};
