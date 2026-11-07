import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            pro: boolean;
            unreadChats: number;
            coach: {
                id: number;
                name: string;
                avatar: string | null;
                slug: string;
                user_id: number;
            } | null;
            sidebarOpen: boolean;
            nudge: import('@/components/nudge-banner').NudgePayload | null;
            [key: string]: unknown;
        };
    }
}
