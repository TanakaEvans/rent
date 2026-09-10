import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Megaphone, Clock3, BadgeDollarSign, MousePointerClick, Eye, MessageSquareText, ClipboardCheck, ImagePlay } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';
import { Button } from '@/Components/ui/button';

const money = (value) => `$${Number(value || 0).toFixed(2)}`;
const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

const typeLabel = {
    featured: 'Featured',
    top: 'Top placement',
    homepage: 'Homepage',
    premium_badge: 'Premium badge',
};

export default function OwnerAdvertising({ enabled = true, approval_required = true, packages = [], placements = [], properties = [] }) {
    const [propertyId, setPropertyId] = useState('');
    const [packageId, setPackageId] = useState('');

    const active = placements.filter((p) => ['active', 'paused'].includes(p.status));
    const pending = placements.filter((p) => p.status === 'reserved');
    const spent = placements.filter((p) => p.paid_at).reduce((sum, p) => sum + Number(p.amount || 0), 0);
    const clicks = placements.reduce((sum, p) => sum + Number(p.stats?.clicks || 0), 0);

    const bookable = properties.filter((p) => !p.promotion_open);
    const selectedPackage = packages.find((p) => String(p.id) === String(packageId));

    const book = () => {
        if (!propertyId || !packageId) return;
        router.post(route('owner.advertising.store'), { property_id: propertyId, package_id: packageId }, {
            preserveScroll: true,
            onSuccess: () => {
                setPropertyId('');
                setPackageId('');
            },
        });
    };

    return (
        <MainLayout title="Advertising">
            <Head title="Advertising" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Advertising</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Push your listings above organic results with paid placements.
                    {approval_required ? ' Orders are activated once staff approve the payment.' : ' Orders activate immediately on booking.'}
                </p>
            </div>

            {!enabled && (
                <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">
                    Promoted listings are temporarily unavailable. Your existing placements keep running.
                </div>
            )}

            <div className="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={Megaphone} label="Active promotions" value={String(active.length)} hint="Live or frozen placements" tone="emerald" />
                <StatCard icon={Clock3} label="Awaiting approval" value={String(pending.length)} hint="Reserved orders in the queue" tone="amber" />
                <StatCard icon={BadgeDollarSign} label="Total spent" value={money(spent)} hint="Across all placements" tone="teal" />
                <StatCard icon={MousePointerClick} label="Placement clicks" value={String(clicks)} hint="On promoted listings" tone="violet" />
            </div>

            <div className="grid gap-8 lg:grid-cols-5">
                <section className="surface h-fit p-6 lg:col-span-2">
                    <h3 className="mb-1 text-lg font-extrabold tracking-tight text-foreground">Promote a listing</h3>
                    <p className="mb-5 text-sm text-muted-foreground">Pick an available listing and a package.</p>

                    {properties.length === 0 || bookable.length === 0 ? (
                        <EmptyState
                            icon={ImagePlay}
                            title={properties.length === 0 ? 'No published listings' : 'All listings promoted'}
                            description={properties.length === 0 ? 'Publish an available property first, then promote it here.' : 'Every available listing already holds a placement. Let a window expire or ask staff to cancel one.'}
                            className="bg-background rounded-xl"
                        />
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Listing</label>
                                <select className="field" value={propertyId} onChange={(e) => setPropertyId(e.target.value)}>
                                    <option value="">Select a listing…</option>
                                    {bookable.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.title} — {money(p.price)}/mo
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-muted-foreground">Package</label>
                                <select className="field" value={packageId} onChange={(e) => setPackageId(e.target.value)}>
                                    <option value="">Select a package…</option>
                                    {packages.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} — {money(p.price)} / {p.duration_days} days
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {selectedPackage && (
                                <p className="rounded-lg bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground">
                                    {selectedPackage.description}
                                </p>
                            )}

                            <Button disabled={!propertyId || !packageId || !enabled} onClick={book} className="w-full">
                                Book promotion{selectedPackage ? ` — ${money(selectedPackage.price)}` : ''}
                            </Button>
                            {approval_required && (
                                <p className="text-center text-[11px] font-medium text-muted-foreground">
                                    The window opens once staff approve the order.
                                </p>
                            )}
                        </div>
                    )}
                </section>

                <section className="lg:col-span-3">
                    <h3 className="mb-3 px-1 text-sm font-bold uppercase tracking-wider text-muted-foreground">My placements</h3>

                    {placements.length === 0 ? (
                        <EmptyState
                            icon={Megaphone}
                            title="No placements yet"
                            description="Book your first promotion and its performance stats will appear here."
                        />
                    ) : (
                        <div className="space-y-4">
                            {placements.map((p) => (
                                <article key={p.id} className="surface p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h4 className="font-extrabold tracking-tight text-foreground">{p.property?.title || '—'}</h4>
                                                <StatusBadge status={p.status} />
                                            </div>
                                            <div className="mt-1 text-xs font-semibold text-muted-foreground">
                                                {p.package?.name || '—'} · {typeLabel[p.package?.placement_type] || p.package?.placement_type} · {money(p.amount)}
                                            </div>
                                        </div>
                                        <div className="text-right text-xs text-muted-foreground">
                                            <div className="font-semibold">
                                                {p.starts_at ? fmt(p.starts_at) : '—'} → {p.ends_at ? fmt(p.ends_at) : '—'}
                                            </div>
                                            <div className="mt-0.5">
                                                Paid {fmt(p.paid_at)}
                                                {Number(p.credit_amount || 0) > 0 && (
                                                    <span className="ml-2 font-bold text-emerald-600">credit {money(p.credit_amount)}</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {[
                                            { icon: Eye, label: 'impressions', value: p.stats?.impressions || 0 },
                                            { icon: MousePointerClick, label: 'clicks', value: p.stats?.clicks || 0 },
                                            { icon: MessageSquareText, label: 'enquiries', value: p.stats?.enquiries || 0 },
                                            { icon: ClipboardCheck, label: 'applications', value: p.stats?.applications || 0 },
                                        ].map(({ icon: Icon, label, value }) => (
                                            <span key={label} className="inline-flex items-center gap-1.5 rounded-full bg-muted/60 px-2.5 py-1 text-[11px] font-bold text-muted-foreground">
                                                <Icon className="h-3.5 w-3.5 text-primary" /> {value} {label}
                                            </span>
                                        ))}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </MainLayout>
    );
}