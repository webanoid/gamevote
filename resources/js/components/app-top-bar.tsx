import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, logout } from '@/routes';
import { edit } from '@/routes/profile';

export default function AppTopBar() {
    const { auth, name } = usePage().props;

    return (
        <header className="sticky top-0 z-20 border-b bg-background/85 backdrop-blur">
            <div className="mx-auto flex h-14 w-full max-w-3xl items-center gap-2 px-4">
                <Link
                    href={dashboard()}
                    prefetch
                    className="mr-auto flex items-center gap-2 font-semibold"
                >
                    <AppLogoIcon className="size-6 text-red-500" />
                    {name}
                </Link>

                <span className="hidden text-sm text-muted-foreground sm:inline">
                    @{auth.user.username}
                </span>

                <Button variant="ghost" size="icon" asChild>
                    <Link href={edit()} prefetch aria-label="Settings">
                        <Settings />
                    </Link>
                </Button>

                <Button variant="outline" size="sm" asChild>
                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => router.flushAll()}
                        data-test="logout-button"
                    >
                        <LogOut />
                        Log out
                    </Link>
                </Button>
            </div>
        </header>
    );
}
