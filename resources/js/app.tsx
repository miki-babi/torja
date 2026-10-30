import { createInertiaApp } from '@inertiajs/react';
import { TrackingScripts } from '@/components/tracking/tracking-scripts';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdminLayout from '@/layouts/admin-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { MobileShell } from '@/components/member/mobile-shell';

const appName = import.meta.env.VITE_APP_NAME || 'Torja';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'calculator':
            case name.startsWith('calculator/'):
            case name === 'pro':
            case name === 'plan/wizard':
            case name === 'plan/review':
            case name === 'plan/preview':
            case name.startsWith('member/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [MobileShell, SettingsLayout];
            case name.startsWith('admin/'):
                return AdminLayout;
            default:
                return null;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <TrackingScripts />
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
