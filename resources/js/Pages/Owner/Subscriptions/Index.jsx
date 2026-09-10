import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { BadgeDollarSign, Sparkles, Check, ArrowUpRight, ArrowDownRight } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Progress } from '@/Components/ui/progress';

const money = (value) => `$${Number(value || 0).toFixed(2)}`;
const cycleLabel = (cycle) => (cycle === 'annual' ? '/yr' : '/mo');
const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
const limitLabel = (limit) => (limit === null ? 'Unlimited listings' : `${limit} ${limit === 1 ? 'listing' : 'listings'}`);

export default function OwnerSubscriptionsIndex({ current, usage, plans = [], invoices = [], proration_mode = 'credit_new_invoice' }) {
    const plan = current?.plan;
    const isCurrent = (id) => plan?.id === id;
    const blocked = current?.status === 'suspended';
    const usedPct = usage.limit ? Math.min(100, Math.round(((usage.published || 0) / usage.limit) * 100)) : 0;

    const subscribe = (planId) => router.post(route('owner.subscriptions.subscribe'), { plan_id: planId });

    const prorationCopy = {
        charge_difference: 'Upgrades apply instantly at the price difference; downgrades take effect at the end of your current cycle.',
        credit_new_invoice: 'Upgrades apply instantly at a pro-rated rate; downgrades take effect at the end of your current cycle.',
        apply_at_renewal: 'Plan changes take effect at the end of your current cycle.',
    };

    return (
        <MainLayout title="Subscription">
            <Head title="Subscription" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Subscription</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Publish more listings whenever you need them. {prorationCopy[proration_mode] || prorationCopy.credit_new_invoice}
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <section className="surface p-6 lg:col-span-1">
                    <div className="mb-4 flex items-center gap-3">
                        <span className="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-primary">
                            <BadgeDollarSign className="h-6 w-6" />
                        </span>
                        <div className="min-w-0">
                            <h3 className="truncate text-lg font-extrabold text-foreground">{plan?.name || 'Free'}</h3>
                            <StatusBadge status={current?.status || 'active'} />
                        </div>
                    </div>

                    <div className="mb-5">
                        <div className="flex items-end justify-between gap-2">
                            <span className="text-2xl font-extrabold text-foreground">
                                {money(plan?.price)}
                                <span className="text-sm font-semibold text-muted-foreground">{cycleLabel(plan?.billing_cycle)}</span>
                            </span>
                            <span className="text-xs font-semibold text-muted-foreground">Renews {fmt(current?.ends_at)}</span>
                        </div>
                    </div>

                    <div className="mb-2 flex items-center justify-between text-sm">
                        <span className="font-semibold text-foreground">Listings in use</span>
                        <span className="font-bold text-foreground">
                            {usage.published} / {usage.limit === null ? '∞' : usage.limit}
                        </span>
                    </div>
                    <Progress value={usedPct} className="h-2" />

                    {blocked && (
                        <p className="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
                            Your subscription is suspended. Choose a plan below to resume publishing.
                        </p>
                    )}

                    <ul className="mt-5 space-y-2 text-sm text-muted-foreground">
                        <li className="flex items-center gap-2">
                            <Check className="h-4 w-4 text-emerald-500" /> {limitLabel(plan?.listing_limit)}
                        </li>
                        <li className="flex items-center gap-2">
                            <Check className="h-4 w-4 text-emerald-500" /> {plan?.featured_slots || 0} featured slots
                        </li>
                        {(plan?.features || []).slice(0, 5).map((feature) => (
                            <li key={feature.id} className="flex items-center gap-2">
                                <Check className="h-4 w-4 text-emerald-500" /> {feature.label}
                            </li>
                        ))}
                        {(plan?.features || []).length > 5 && (
                            <li className="text-xs font-medium text-muted-foreground">+{(plan?.features || []).length - 5} more</li>
                        )}
                    </ul>
                </section>

                <section className="lg:col-span-2">
                    <h3 className="mb-3 px-1 text-sm font-bold uppercase tracking-wider text-muted-foreground">Compare plans</h3>
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        {plans.map((plan) => {
                            const currentPlan = isCurrent(plan.id);
                            const upgrade = !currentPlan && Number(plan.price || 0) >= Number(current?.plan?.price || 0);
                            return (
                                <article key={plan.id} className="surface flex flex-col p-5">
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <h4 className="font-extrabold text-foreground">{plan.name}</h4>
                                        {currentPlan && (
                                            <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold capitalize text-emerald-700">
                                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> current
                                            </span>
                                        )}
                                        {plan.analytics_enabled && !currentPlan && <Sparkles className="h-4 w-4 text-emerald-500" />}
                                    </div>
                                    <div className="mb-4">
                                        <span className="text-xl font-extrabold text-foreground">{money(plan.price)}</span>
                                        <span className="text-xs font-semibold text-muted-foreground">{cycleLabel(plan.billing_cycle)}</span>
                                    </div>
<ul className="mb-5 space-y-1.5 text-sm text-muted-foreground">
                        <li>{limitLabel(plan.listing_limit)}</li>
                        <li>{plan.featured_slots || 0} featured slots</li>
                        {(plan.features || []).slice(0, 3).map((feature) => (
                            <li key={feature.id}>{feature.label}</li>
                        ))}
                        {(plan.features || []).length > 3 && <li>+{(plan.features || []).length - 3} more</li>}
                    </ul>
                                    <Button
                                        className="mt-auto w-full"
                                        variant={currentPlan ? 'outline' : 'default'}
                                        disabled={currentPlan}
                                        onClick={() => subscribe(plan.id)}
                                    >
                                        {currentPlan ? 'Current plan' : upgrade ? (<><ArrowUpRight className="size-4" /> Upgrade</>) : (<><ArrowDownRight className="size-4" /> Switch</>)}
                                    </Button>
                                </article>
                            );
                        })}
                    </div>
                </section>
            </div>

            {invoices.length > 0 && (
                <section className="surface mt-8 p-6">
                    <h3 className="mb-4 text-sm font-bold uppercase tracking-wider text-muted-foreground">Recent payments</h3>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-border text-xs uppercase tracking-wider text-muted-foreground">
                                    <th className="py-2 pr-4 font-bold">Invoice</th>
                                    <th className="py-2 pr-4 font-bold">Amount</th>
                                    <th className="py-2 pr-4 font-bold">Receipt</th>
                                    <th className="py-2 font-bold">Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.map((invoice) => (
                                    <tr key={invoice.id} className="border-b border-border/60 last:border-0">
                                        <td className="py-2.5 pr-4 font-mono text-xs font-bold text-foreground">{invoice.invoice_no}</td>
                                        <td className="py-2.5 pr-4 font-semibold text-foreground">{money(invoice.amount)}</td>
                                        <td className="py-2.5 pr-4 font-mono text-xs text-muted-foreground">{invoice.receipt_no || '—'}</td>
                                        <td className="py-2.5 text-muted-foreground">{fmt(invoice.paid_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </MainLayout>
    );
}