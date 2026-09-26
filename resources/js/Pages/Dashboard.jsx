import { Head, Link, usePage } from '@inertiajs/react';
import { Home, KeyRound, Sparkles } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <MainLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="surface mx-auto max-w-2xl p-8 text-center">
                <span className="kicker border-primary/25 bg-primary/10 text-primary">
                    <Sparkles className="h-3.5 w-3.5" /> Welcome
                </span>
                <h2 className="mt-3 text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">
                    Hello, {auth?.user?.name}
                </h2>
                <p className="mt-2 text-sm text-muted-foreground">
                    Your account does not have a workspace yet. If you expected to see one, contact ZimRent support so they can give your account the right access.
                </p>
                <div className="mt-6 flex flex-wrap justify-center gap-3">
                    <Link href={route('home')} className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:opacity-90">
                        <Home className="h-4 w-4" /> Browse the marketplace
                    </Link>
                    <Link href={route('password.change')} className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2.5 text-sm font-semibold text-foreground hover:bg-muted">
                        <KeyRound className="h-4 w-4" /> Change password
                    </Link>
                </div>
            </div>
        </MainLayout>
    );
}
