import { createInertiaApp, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'GameVote';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
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

const httpErrorMessages: Record<number, string> = {
    419: 'Your session has expired. Refresh the page and try again.',
    429: 'You’re doing that too often. Wait a moment and try again.',
    503: 'The app is down for maintenance. Please try again shortly.',
};

// Surface failures as toasts instead of failing silently or leaving the page.
router.on('networkError', () => {
    toast.error(
        'Couldn’t reach the server. Check your connection and try again.',
    );
});

router.on('httpException', (event) => {
    // Keep Laravel's detailed error page in development.
    if (import.meta.env.DEV) {
        return;
    }

    event.preventDefault();
    toast.error(
        httpErrorMessages[event.detail.response.status] ??
            'Something went wrong on our end. Please try again.',
    );
});
