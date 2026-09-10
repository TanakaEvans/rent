import { Head, Link } from '@inertiajs/react';
import {
    Building2, Eye, Heart, MessageSquareText, ClipboardCheck, KeyRound,
    CalendarCheck, Ban, Star, TrendingUp, BarChart3, MapPin, ArrowRight,
} from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatCard from '@/Components/Shared/StatCard';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import PropertyArt from '@/Components/Shared/PropertyArt';

const formatPrice = (value) => '$' + Number(value).toLocaleString();

function TrendChart({ data = [] }) {
    const max = Math.max(...data.map((d) => d.views), 1);
    const total = data.reduce((s, d) => s + d.views, 0);
    return (
        <div>
            <div className="flex items-end gap-[3px]">
                {data.map((d) => (
                    <div
                        key={d.day}
                        className="flex-1 rounded-t bg-emerald-500/80 transition-colors hover:bg-emerald-500"
                        style={{ height: `${Math.max((d.views / max) * 100, d.views > 0 ? 8 : 1.5)}%` }}
                        title={`${d.day}: ${d.views} view${d.views === 1 ? '' : 's'}`}
                    />
                ))}
            </div>
            <p className="mt-3 text-xs font-semibold text-muted-foreground">
                {total.toLocaleString()} page view{total === 1 ? '' : 's'} on your listings over the last 30 days.
            </p>
        </div>
    );
}

export default function OwnerAnalytics({ analytics = {} }) {
    const totals = analytics.totals || {};
    const properties = analytics.properties || [];

    const statCards = [
        { key: 'properties', label: 'My Properties', value: totals.properties ?? 0, icon: Building2, tone: 'emerald', routeName: 'owner.properties.index' },
        { key: 'listed', label: 'Available', value: totals.listed ?? 0, icon: KeyRound, tone: 'teal', routeName: 'owner.properties.index' },
        { key: 'reserved', label: 'Reserved', value: totals.reserved ?? 0, icon: CalendarCheck, tone: 'amber', routeName: 'owner.properties.index' },
        { key: 'occupied', label: 'Occupied', value: totals.occupied ?? 0, icon: Building2, tone: 'violet', routeName: 'owner.properties.index' },
        { key: 'unavailable', label: 'Unavailable', value: totals.unavailable ?? 0, icon: Ban, tone: 'slate', routeName: 'owner.properties.index' },
        { key: 'views', label: 'Total Views', value: (totals.views ?? 0).toLocaleString(), icon: Eye, tone: 'sky' },
        { key: 'favourites', label: 'Favourites', value: totals.favourites ?? 0, icon: Heart, tone: 'rose' },
        { key: 'enquiries', label: 'Enquiries', value: totals.enquiries ?? 0, icon: MessageSquareText, tone: 'indigo', routeName: 'owner.enquiries.index' },
        { key: 'applications', label: 'Applications', value: totals.applications ?? 0, icon: ClipboardCheck, tone: 'violet', routeName: 'owner.applications.index' },
    ];

    return (
        <MainLayout title="Marketplace Analytics">
            <Head title="Marketplace Analytics" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Listing Performance</h2>
                    <p className="mt-1 text-sm text-muted-foreground">How your properties are performing across the marketplace.</p>
                </div>
                <Link href={route('owner.properties.index')} className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-4 py-2 text-sm font-bold text-foreground shadow-sm transition-colors hover:border-primary/40 hover:text-primary">
                    Manage properties <ArrowRight className="h-4 w-4" />
                </Link>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {statCards.map((card) => <StatCard key={card.key} {...card} />)}
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <section className="surface p-6 lg:col-span-2">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                <TrendingUp className="h-4 w-4 text-primary" /> Views over time
                            </p>
                            <h3 className="mt-1 text-lg font-extrabold tracking-tight">Last 30 days</h3>
                        </div>
                    </div>
                    <div className="mt-5 h-28">
                        <TrendChart data={analytics.trend || []} />
                    </div>
                </section>

                <section className="surface p-6">
                    <div className="flex items-center justify-between">
                        <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            <Star className="h-4 w-4 text-primary" /> Top performer
                        </p>
                    </div>
                    {analytics.topProperty ? (
                        <>
                            <h3 className="mt-2 text-lg font-extrabold tracking-tight">{analytics.topProperty.title}</h3>
                            <p className="mt-1 text-xs font-medium text-muted-foreground">
                                {analytics.topProperty.views.toLocaleString()} views
                            </p>
                            <Link href={route('owner.properties.show', analytics.topProperty.id)} className="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-primary hover:underline">
                                View property <ArrowRight className="h-4 w-4" />
                            </Link>
                        </>
                    ) : (
                        <p className="mt-4 text-sm text-muted-foreground">List a property to start tracking its performance.</p>
                    )}
                </section>
            </div>

            <div className="mt-9 flex items-end justify-between gap-3">
                <div>
                    <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        <BarChart3 className="h-4 w-4 text-primary" /> Per-property breakdown
                    </p>
                    <h3 className="mt-1 text-lg font-extrabold tracking-tight">Engagement by listing</h3>
                </div>
                <span className="text-xs font-semibold text-muted-foreground">{properties.length} total</span>
            </div>

            {properties.length === 0 ? (
                <div className="mt-4">
                    <EmptyState
                        icon={BarChart3}
                        title="No listings yet"
                        description="Once you list properties, their views, favourites, enquiries and applications will appear here."
                    />
                </div>
            ) : (
                <div className="surface mt-3 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/60 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <th className="px-5 py-3">Property</th>
                                    <th className="px-5 py-3">Views</th>
                                    <th className="px-5 py-3">Favourites</th>
                                    <th className="px-5 py-3">Enquiries</th>
                                    <th className="px-5 py-3">Applications</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {properties.map((property) => (
                                    <tr key={property.id} className="transition-colors hover:bg-muted/40">
                                        <td className="px-5 py-3.5">
                                            <Link href={route('owner.properties.show', property.id)} className="flex items-center gap-3">
                                                <div className="h-11 w-14 shrink-0 overflow-hidden rounded-lg">
                                                    <PropertyArt property={property} />
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="truncate font-semibold text-foreground">{property.title}</div>
                                                    <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                                                        <MapPin className="h-3 w-3 shrink-0" />
                                                        <span className="truncate">
                                                            {[property.suburb, property.city].filter(Boolean).join(', ') || 'Location on request'}
                                                        </span>
                                                        <span className="shrink-0">· {formatPrice(property.price)}/mo</span>
                                                    </div>
                                                </div>
                                            </Link>
                                        </td>
                                        <td className="px-5 py-3.5 font-bold text-foreground">{(property.views_count ?? 0).toLocaleString()}</td>
                                        <td className="px-5 py-3.5 font-semibold text-muted-foreground">{property.favourites_count ?? 0}</td>
                                        <td className="px-5 py-3.5 font-semibold text-muted-foreground">{property.enquiries_count ?? 0}</td>
                                        <td className="px-5 py-3.5 font-semibold text-muted-foreground">{property.applications_count ?? 0}</td>
                                        <td className="px-5 py-3.5"><StatusBadge status={property.status} /></td>
                                        <td className="px-5 py-3.5 text-right">
                                            <Link href={route('owner.properties.show', property.id)} className="text-xs font-bold text-primary hover:underline">
                                                Manage
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </MainLayout>
    );
}