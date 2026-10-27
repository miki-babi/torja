import type { InertiaLinkProps } from '@inertiajs/react';
import {
    Activity,
    Apple,
    Carrot,
    ChartLine,
    CreditCard,
    Dumbbell,
    Globe,
    Languages,
    LayoutDashboard,
    Users,
    UtensilsCrossed,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { analytics, dashboard } from '@/routes/admin';
import { index as foodsIndex } from '@/routes/admin/foods';
import { index as ingredientsIndex } from '@/routes/admin/ingredients';
import { index as languagesIndex } from '@/routes/admin/languages';
import { index as mealProgramsIndex } from '@/routes/admin/meal-programs';
import { index as paymentsIndex } from '@/routes/admin/payments';
import { index as programsIndex } from '@/routes/admin/programs';
import { index as trackingIndex } from '@/routes/admin/tracking';
import { index as translationsIndex } from '@/routes/admin/translations';
import { index as usersIndex } from '@/routes/admin/users';

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
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutDashboard,
                exact: true,
            },
            { title: 'Analytics', href: analytics(), icon: ChartLine },
        ],
    },
    {
        label: 'People',
        items: [{ title: 'Users', href: usersIndex(), icon: Users }],
    },
    {
        label: 'Sales',
        items: [{ title: 'Payments', href: paymentsIndex(), icon: CreditCard }],
    },
    {
        label: 'Content',
        items: [
            { title: 'Programs', href: programsIndex(), icon: Dumbbell },
            {
                title: 'Meal programs',
                href: mealProgramsIndex(),
                icon: UtensilsCrossed,
            },
            { title: 'Foods', href: foodsIndex(), icon: Apple },
            { title: 'Ingredients', href: ingredientsIndex(), icon: Carrot },
        ],
    },
    {
        label: 'Localization',
        items: [
            { title: 'Languages', href: languagesIndex(), icon: Languages },
            { title: 'Translations', href: translationsIndex(), icon: Globe },
        ],
    },
    {
        label: 'System',
        items: [{ title: 'Tracking', href: trackingIndex(), icon: Activity }],
    },
];
