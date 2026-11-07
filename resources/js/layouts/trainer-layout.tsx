import { TrainerHeader } from '@/components/trainer/trainer-header';
import { TrainerSidebar } from '@/components/trainer/trainer-sidebar';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import type { AppLayoutProps } from '@/types';

export default function TrainerLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <div className="admin">
            <AppShell variant="sidebar">
                <TrainerSidebar />
                <AppContent
                    variant="sidebar"
                    className="min-w-0 overflow-x-clip"
                >
                    <TrainerHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
            </AppShell>
        </div>
    );
}
