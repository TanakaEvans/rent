import { Head, Link } from '@inertiajs/react';
import {
    Building2, Home, Star, BadgeCheck, Eye, Flag, Save, MessageSquareText,
    ClipboardCheck, UserPlus, TrendingUp, Ruler, DollarSign, MapPin,
} from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatCard from '@/Components/Shared/StatCard';

const typeLabels = {
    house: 'House',
    flat: 'Flat / Apartment',
    townhouse: 'Townhouse',
    cottage: 'Cottage',
    room: 'Room',
    commercial: 'Commercial',
    land: 'Land',
};

const formatPrice = (value) => '$' + Number(value).toLocaleString();

function TrendChart({ data = [], className }) {
    const max = Math.max(...data.map((d) => d.views), 1);
    return (
        <div className={`mt-4 flex items-end gap-[3px] ${className || ''}`}>
            {data.map((d, i) => (
                <div key={d.day} className="group relative flex-1">
                    <div
                        className="w-full rounded-t bg-emerald-500/80 transition-colors hover:bg-emerald-500"
                        style={{ height: `${Math.max((d.views / max) * 100, d.views > 0 ? 8 : 1.5)}%` }}
                        title={`${d.day}: ${d.views} view${d.views === 1 ? '' : 's'}`}
                    />
                    {(i === 0 || i === data.length - 1 || i % 6 === 0) && (
                        <span className="pointer-events-none absolute -bottom-4 left-1/2 -translate-x-1/2 text-[9px] font-semibold text-muted-foreground">
                            {d.day.slice(5)}
                        </span>
                    )}
                </div>
            ))}
        </div>
    );
}

export default function AdminMarketplaceAnalytics({ analytics = {} }) {
    const totals = analytics.totals || {};
    const byType = Object.entries(analytics.byType || {}).sort((a, b) => b[1] - a[1]);
    const byTypeTotal = Object.values(analytics.byType || {}).reduce((sum, n) => sum + n, 0) || 1;
    const inventory = (analytics.totals || {}).listings || 0;

    const statCards = [
        { key: 'listings', label: 'Total Listings', value: totals.listings ?? 0, icon: Building2, tone: 'emerald', hint: `${totals.listed ?? 0} currently available` },
        { key: 'featured', label: 'Featured', value: totals.featured ?? 0, icon: Star, tone: 'amber' },
        { key: 'verified', label: 'Verified Listings', value: totals.verified ?? 0, icon: BadgeCheck, tone: 'teal' },
        { key: 'views30d', label: 'Views (30 days)', value: totals.views30d ?? 0, icon: Eye, tone: 'sky' },
        { key: 'openReports', label: 'Open Reports', value: totals.openReports ?? 0, icon: Flag, tone: 'rose', routeName: 'admin.marketplace.reports.index' },
        { key: 'savedSearches', label: 'Saved Searches', value: totals.savedSearches ?? 0, icon: Save, tone: 'violet' },
        { key: 'enquiries30d', label: 'Enquiries (30d)', value: totals.enquiries30d ?? 0, icon: MessageSquareText, tone: 'indigo' },
        { key: 'applications30d', label: 'Applications (30d)', value: totals.applications30d ?? 0, icon: ClipboardCheck, tone: 'slate' },
        { key: 'newTenants30d', label: 'New Tenants (30d)', value: totals.newTenants30d ?? 0, icon: UserPlus, tone: 'teal' },
    ];

    return (
        <AdminLayout title="Marketplace Analytics">
            <Head title="Marketplace Analytics" />

            <div className="mb-6">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Marketplace Health</h2>
                <p className="mt-1 text-sm text-muted-foreground">Platform-wide performance of the rental marketplace.</p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {statCards.map((card) => <StatCard key={card.key} {...card} />)}
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <StatCard icon={DollarSign} label="Average monthly rent" value={formatPrice(analytics.avgPrice ?? 0)} tone="emerald" hint="Across currently available listings" />
                <StatCard icon={Ruler} label="Average per m² (monthly)" value={formatPrice(analytics.avgPricePerM2 ?? 0)} tone="indigo" hint="Available listings with a measured size" />
            </div>

            <div className="mt-8 grid gap-5 lg:grid-cols-3">
                <section className="surface p-6 lg:col-span-2">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                <TrendingUp className="h-4 w-4 text-primary" /> Listing views
                            </p>
                            <h3 className="mt-1 text-lg font-extrabold tracking-tight">Last 30 days</h3>
                        </div>
                        <span className="text-xs font-semibold text-muted-foreground">{(analytics.trend || []).reduce((s, d) => s + d.views, 0).toLocaleString()} total views</span>
                    </div>
                    <div className="h-28">
                        <TrendChart data={analytics.trend || []} className="h-24" />
                    </div>
                </section>

                <section className="surface p-6">
                    <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        <Home className="h-4 w-4 text-primary" /> Listings by type
                    </p>
                    <h3 className="mt-1 text-lg font-extrabold tracking-tight">Inventory mix</h3>
                    {byType.length === 0 ? (
                        <p className="mt-4 text-sm text-muted-foreground">No listed properties yet.</p>
                    ) : (
                        <ul className="mt-4 space-y-2.5">
                            {byType.map(([type, count]) => (
                                <li key={type}>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="font-semibold text-foreground">{typeLabels[type] || type}</span>
                                        <span className="font-bold text-muted-foreground">{count}</span>
                                    </div>
                                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-muted">
                                        <div className="brand-gradient h-full rounded-full" style={{ width: `${(count / byTypeTotal) * 100}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-2">
                <section className="surface p-6">
                    <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        <Eye className="h-4 w-4 text-primary" /> Most viewed listings
                    </p>
                    <h3 className="mt-1 text-lg font-extrabold tracking-tight">Top 5 this month</h3>
                    {(analytics.mostViewed || []).length === 0 ? (
                        <p className="mt-4 text-sm text-muted-foreground">No views recorded yet.</p>
                    ) : (
                        <ul className="mt-4 divide-y divide-border">
                            {(analytics.mostViewed || []).map((item, i) => (
                                <li key={item.id} className="flex items-center justify-between gap-3 py-3">
                                    <div className="min-w-0">
                                        <span className="mr-2 text-xs font-extrabold text-muted-foreground/60">#{i + 1}</span>
                                        <Link href={route('property.show', item.id)} className="text-sm font-bold text-foreground hover:text-primary hover:underline">
                                            {item.title}
                                        </Link>
                                        <p className="mt-0.5 flex items-center gap-1 truncate text-xs text-muted-foreground">
                                            <MapPin className="h-3 w-3 shrink-0" /> {[item.suburb, item.city].filter(Boolean).join(', ') || 'Location on request'}
                                        </p>
                                    </div>
                                    <span className="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-extrabold text-emerald-700 ring-1 ring-emerald-200">
                                        {item.views.toLocaleString()} views
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="surface p-6">
                    <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        <MapPin className="h-4 w-4 text-primary" /> Top searched areas
                    </p>
                    <h3 className="mt-1 text-lg font-extrabold tracking-tight">Where views come from</h3>
                    {(analytics.topAreas || []).length === 0 ? (
                        <p className="mt-4 text-sm text-muted-foreground">No area data yet.</p>
                    ) : (
                        <ul className="mt-4 divide-y divide-border">
                            {(analytics.topAreas || []).map((area, i) => (
                                <li key={i} className="flex items-center justify-between gap-3 py-3">
                                    <span className="truncate text-sm font-bold text-foreground">{area.area}</span>
                                    <span className="shrink-0 text-xs font-bold text-muted-foreground">{area.views.toLocaleString()} view{area.views === 1 ? '' : 's'}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {inventory > 0 && (
                        <div className="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 ring-1 ring-emerald-200">
                            <span className="font-extrabold">{((totals.views30d ?? 0) / inventory).toFixed(1)}</span> average views per listing over the past 30 days.
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}