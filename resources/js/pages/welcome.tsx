import { Head, Link, usePage } from '@inertiajs/react';
import { Gamepad2, ThumbsUp, CalendarCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const steps = [
    {
        icon: Gamepad2,
        title: 'Suggest',
        description: 'Add a game you’d like to see in the office library.',
    },
    {
        icon: ThumbsUp,
        title: 'Vote',
        description: 'Back the games you want most. Top votes rise first.',
    },
    {
        icon: CalendarCheck,
        title: 'One a day',
        description: 'Each day you get one action: a vote or a new game.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-4xl items-center justify-between p-4 md:p-6">
                    <div className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="size-6 fill-current" />
                        GameVote
                    </div>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Open library</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>Sign up</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col justify-center gap-10 p-4 md:p-6">
                    <div className="max-w-xl space-y-4">
                        <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">
                            Pick the next games for the office library
                        </h1>
                        <p className="text-muted-foreground">
                            Suggest titles, vote for your favourites, and see
                            what the team wants most.
                        </p>
                        <Button size="lg" asChild>
                            <Link href={auth.user ? dashboard() : register()}>
                                {auth.user ? 'Open the library' : 'Get started'}
                            </Link>
                        </Button>
                    </div>

                    <ul className="grid gap-4 sm:grid-cols-3">
                        {steps.map(({ icon: Icon, title, description }) => (
                            <li
                                key={title}
                                className="space-y-2 rounded-xl border p-4"
                            >
                                <Icon className="size-5 text-muted-foreground" />
                                <p className="font-medium">{title}</p>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </li>
                        ))}
                    </ul>
                </main>
            </div>
        </>
    );
}
