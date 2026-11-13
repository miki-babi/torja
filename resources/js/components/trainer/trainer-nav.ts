import type { InertiaLinkProps } from '@inertiajs/react';
import {
    Apple,
    BicepsFlexed,
    Bot,
    ClipboardCheck,
    Dumbbell,
    LayoutDashboard,
    Megaphone,
    MessageCircle,
    Settings,
    Tag,
    UserPlus,
    UserRound,
    Users,
    UtensilsCrossed,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { messages } from '@/routes/member';
import { dashboard, earnings } from '@/routes/trainer';
import { edit as botEdit } from '@/routes/trainer/bot';
import { index as broadcastsIndex } from '@/routes/trainer/broadcasts';
import { index as checkInsIndex } from '@/routes/trainer/check-ins';
import { index as clientsIndex } from '@/routes/trainer/clients';
import { index as exercisesIndex } from '@/routes/trainer/exercises';
import { index as foodsIndex } from '@/routes/trainer/foods';
import { index as leadsIndex } from '@/routes/trainer/leads';
import { edit as leadFormEdit } from '@/routes/trainer/lead-form';
import { index as mealProgramsIndex } from '@/routes/trainer/meal-programs';
import { index as plansIndex } from '@/routes/trainer/plans';
import { index as programsIndex } from '@/routes/trainer/programs';
import { edit as profileEdit } from '@/routes/trainer/profile';
import { edit as settingsEdit } from '@/routes/trainer/settings';

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
                title: 'Exercises',
                href: exercisesIndex(),
                icon: BicepsFlexed,
            },
            {
                title: 'Meal programs',
                href: mealProgramsIndex(),
                icon: UtensilsCrossed,
            },
            { title: 'Foods', href: foodsIndex(), icon: Apple },
            { title: 'Coaching plans', href: plansIndex(), icon: Tag },
        ],
    },
    {
        label: 'Money',
        items: [{ title: 'Earnings', href: earnings(), icon: Wallet }],
    },
    {
        label: 'Engagement',
        items: [
            { title: 'Leads', href: leadsIndex(), icon: UserPlus },
            {
                title: 'Claim form',
                href: leadFormEdit(),
                icon: ClipboardCheck,
            },
            {
                title: 'Check-ins',
                href: checkInsIndex(),
                icon: ClipboardCheck,
            },
            { title: 'Broadcasts', href: broadcastsIndex(), icon: Megaphone },
            { title: 'Messages', href: messages(), icon: MessageCircle },
        ],
    },
    {
        label: 'Setup',
        items: [
            { title: 'Profile', href: profileEdit(), icon: UserRound },
            { title: 'Bot setup', href: botEdit(), icon: Bot },
            { title: 'Settings', href: settingsEdit(), icon: Settings },
        ],
    },
];
