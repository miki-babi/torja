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
            sidebarOpen: boolean;
            nudge: import('@/components/nudge-banner').NudgePayload | null;
            [key: string]: unknown;
        };
    }
}
