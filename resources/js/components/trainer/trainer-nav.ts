import type { InertiaLinkProps } from '@inertiajs/react';
import {
    Bot,
    Dumbbell,
    LayoutDashboard,
    Megaphone,
    MessageCircle,
    Users,
    UtensilsCrossed,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { messages } from '@/routes/member';
import { dashboard } from '@/routes/trainer';
import { edit as botEdit } from '@/routes/trainer/bot';
import { index as broadcastsIndex } from '@/routes/trainer/broadcasts';
import { index as clientsIndex } from '@/routes/trainer/clients';
import { index as mealProgramsIndex } from '@/routes/trainer/meal-programs';
import { index as programsIndex } from '@/routes/trainer/programs';

export type TrainerNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    exact?: boolean;
};

export type TrainerNavGroup = {
    label: string;
    items: TrainerNavItem[];
};

export const trainerNavGroups: TrainerNavGroup[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutDashboard,
                exact: true,
            },
            { title: 'Clients', href: clientsIndex(), icon: Users },
        ],
    },
    {
        label: 'Plans',
        items: [
            { title: 'Programs', href: programsIndex(), icon: Dumbbell },
            {
                title: 'Meal programs',
                href: mealProgramsIndex(),
                icon: UtensilsCrossed,
            },
        ],
    },
    {
        label: 'Engagement',
        items: [
            { title: 'Broadcasts', href: broadcastsIndex(), icon: Megaphone },
            { title: 'Messages', href: messages(), icon: MessageCircle },
        ],
    },
    {
        label: 'Setup',
        items: [{ title: 'Bot setup', href: botEdit(), icon: Bot }],
    },
];
