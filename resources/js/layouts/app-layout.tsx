import AppTopBar from '@/components/app-top-bar';

export default function AppLayout({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col">
            <AppTopBar />
            <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col px-4 py-6">
                {children}
            </main>
        </div>
    );
}
