import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck, KeyRound, CalendarCheck, Sparkles, CheckCircle2 } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/ui/button';

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
        : '';

const formatPrice = (amount, currency) => {
    const value = Number(amount || 0).toFixed(2);
    return currency === 'ZWL' ? `ZWL ${value}` : `$${value}`;
};

export default function AccessIndex({ status = {} }) {
    const { post, processing } = useForm({});
    const { required = false, active = false, free_until, price, currency = 'USD', period_days = 30, ends_at } = status;

    const buy = (e) => {
        e.preventDefault();
        post(route('access.purchase'), { preserveScroll: true });
    };

    return (
        <MainLayout title="Access Pass">
            <Head title="Access Pass" />

            <div className="mx-auto max-w-2xl">
                <div className="mb-6">
                    <span className="kicker border-primary/25 bg-primary/10 text-primary">
                        <KeyRound className="h-3.5 w-3.5" /> Platform access
                    </span>
                    <h2 className="mt-2 text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Your access pass</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Direct rentals without agent fees — kept affordable with a small access pass.
                    </p>
                </div>

                {/* Current status */}
                <section className="surface overflow-hidden">
                    <div className="flex items-center gap-3 border-b border-border px-5 py-4">
                        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                            <ShieldCheck className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                            <p className="text-sm font-extrabold text-foreground">
                                {active ? 'You have full access' : 'An access pass is required'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {!required && free_until && `Free for everyone until ${formatDate(free_until)}.`}
                                {!required && !free_until && 'Everything is free right now — no pass needed.'}
                                {required && active && ends_at && `Your access is active until ${formatDate(ends_at)}.`}
                                {required && !active && 'Activate a pass below to keep listing, enquiring and applying.'}
                            </p>
                        </div>
                    </div>

                    <dl className="grid grid-cols-1 divide-y divide-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div className="px-5 py-4">
                            <dt className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Status</dt>
                            <dd className="mt-1 flex items-center gap-1.5 text-sm font-bold text-foreground">
                                {active ? <CheckCircle2 className="h-4 w-4 text-emerald-500" /> : <KeyRound className="h-4 w-4 text-amber-500" />}
                                {active ? 'Active' : 'Pass required'}
                            </dd>
                        </div>
                        <div className="px-5 py-4">
                            <dt className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Pass price</dt>
                            <dd className="mt-1 text-sm font-bold text-foreground">
                                {formatPrice(price, currency)}
                                <span className="ml-1 text-xs font-semibold text-muted-foreground">/ {period_days} days</span>
                            </dd>
                        </div>
                        <div className="px-5 py-4">
                            <dt className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                {active && ends_at ? 'Active until' : 'Free until'}
                            </dt>
                            <dd className="mt-1 flex items-center gap-1.5 text-sm font-bold text-foreground">
                                <CalendarCheck className="h-4 w-4 text-primary" />
                                {active && ends_at ? formatDate(ends_at) : (free_until ? formatDate(free_until) : '—')}
                            </dd>
                        </div>
                    </dl>
                </section>

                {/* Purchase */}
                {required && (
                    <section className="brand-gradient-soft mt-6 overflow-hidden rounded-2xl border border-primary/15 p-5">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/15 text-primary">
                                    <Sparkles className="h-6 w-6" />
                                </span>
                                <div>
                                    <p className="text-sm font-extrabold text-foreground">
                                        {active ? 'Extend your access pass' : 'Get your access pass'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatPrice(price, currency)} for {period_days} days of full access.
                                    </p>
                                </div>
                            </div>
                            <form onSubmit={buy}>
                                <Button type="submit" disabled={processing}>
                                    <KeyRound className="h-4 w-4" />
                                    {processing ? 'Activating…' : active ? 'Extend access pass' : 'Get access pass'}
                                </Button>
                            </form>
                        </div>
                    </section>
                )}

                {!required && (
                    <p className="mt-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                        <CheckCircle2 className="h-4 w-4 shrink-0" />
                        No pass needed right now — carry on listing, enquiring and applying for free.
                    </p>
                )}
            </div>
        </MainLayout>
    );
}
