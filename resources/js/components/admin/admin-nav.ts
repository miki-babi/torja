import type { InertiaLinkProps } from '@inertiajs/react';
import { Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
        ],
    },
];
