import type { InertiaLinkProps } from '@inertiajs/react';
import { Banknote, CreditCard, Dumbbell, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { index as exercisesIndex } from '@/routes/admin/exercises';
import { index as paymentsIndex } from '@/routes/admin/payments';
import { index as payoutsIndex } from '@/routes/admin/payouts';
import { index as trainersIndex } from '@/routes/admin/trainers';

export type AdminNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    exact?: boolean;
};

export type AdminNavGroup = {
    label: string;
    items: AdminNavItem[];
};

export const adminNavGroups: AdminNavGroup[] = [
    {
        label: 'Management',
        items: [
            { title: 'Trainers', href: trainersIndex(), icon: Users },
            { title: 'Payments', href: paymentsIndex(), icon: CreditCard },
            { title: 'Payouts', href: payoutsIndex(), icon: Banknote },
            { title: 'Exercises', href: exercisesIndex(), icon: Dumbbell },
        ],
    },
];
