import { Head, Link } from '@inertiajs/react';
import { Home as HomeIcon, Building2, KeyRound, CalendarCheck, FileText, Wallet, MessageSquareText, MapPin, BadgeCheck, ArrowRight, Sparkles, Layers, Banknote } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatCard from '@/Components/Shared/StatCard';
import StatusBadge from '@/Components/Shared/StatusBadge';
import PropertyArt from '@/Components/Shared/PropertyArt';
import EmptyState from '@/Components/Shared/EmptyState';
import { Button, buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

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

const quickLinks = [
    { label: 'List a Property', description: 'Add a new listing', href: route('owner.properties.create'), icon: HomeIcon },
    { label: 'Applications', description: 'Review tenant applications', href: route('owner.applications.index'), icon: FileText },
    { label: 'Enquiries', description: 'Read tenant messages', href: route('owner.enquiries.index'), icon: MessageSquareText },
    { label: 'Viewings', description: 'Schedule and confirm viewing requests', href: route('owner.viewings.index'), icon: CalendarCheck },
    { label: 'Rent & Receipts', description: 'Issue receipts and manage rent', href: route('owner.rent.index'), icon: Wallet },
    { label: 'Plan & Billing', description: 'Manage your subscription', href: route('owner.subscriptions.index'), icon: Sparkles },
];

export default function OwnerDashboard({ stats = {}, subscription = null, properties = [] }) {
    const statCards = [
        { key: 'total_properties', label: 'My Properties', value: stats.total_properties ?? 0, icon: HomeIcon, tone: 'emerald', routeName: 'owner.properties.index' },
        { key: 'available', label: 'Available', value: stats.available ?? 0, icon: KeyRound, tone: 'teal', routeName: 'owner.properties.index' },
        { key: 'occupied', label: 'Occupied', value: stats.occupied ?? 0, icon: Building2, tone: 'violet', routeName: 'owner.properties.index' },
        { key: 'reserved', label: 'Reserved', value: stats.reserved ?? 0, icon: CalendarCheck, tone: 'amber', routeName: 'owner.properties.index' },
        { key: 'applications', label: 'Applications', value: stats.applications ?? 0, icon: FileText, tone: 'rose', routeName: 'owner.applications.index' },
        { key: 'enquiries', label: 'Open Enquiries', value: stats.enquiries ?? 0, icon: MessageSquareText, tone: 'sky', routeName: 'owner.enquiries.index' },
        { key: 'viewings', label: 'Pending Viewings', value: stats.viewings ?? 0, icon: CalendarCheck, tone: 'violet', routeName: 'owner.viewings.index' },
        { key: 'rent_due', label: 'Rent Due', value: formatPrice(stats.rent_due ?? 0), icon: Wallet, tone: 'indigo', routeName: 'owner.rent.index' },
        { key: 'monthly_income', label: 'Monthly Income', value: formatPrice(stats.monthly_income ?? 0), icon: Banknote, tone: 'emerald', routeName: 'owner.rent.index' },
        { key: 'occupancy_rate', label: 'Occupancy', value: `${stats.occupancy_rate ?? 0}%`, icon: Building2, tone: 'teal', routeName: 'owner.properties.index' },
    ];

    const usagePercent = subscription && subscription.limit
        ? Math.min(Math.round(((subscription.usage ?? 0) / subscription.limit) * 100), 100)
        : 0;

    return (
        <MainLayout title="Owner Dashboard">
            <Head title="Owner Dashboard" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Your Rental Portfolio</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Manage listings, view applications, and track your rental income.</p>
                </div>
                <Link href={route('owner.properties.create')} className={cn(buttonVariants())}>
                    <HomeIcon className="h-4 w-4" /> List a New Property
                </Link>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {statCards.map((card) => (
                    <StatCard key={card.key} {...card} />
                ))}
            </div>

            {subscription && (
                <section className="brand-gradient-soft mt-6 overflow-hidden border border-primary/15">
                    <div className="flex flex-wrap items-center justify-between gap-4 p-5">
                        <div className="flex items-center gap-4">
                            <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary/15 text-primary">
                                <Layers className="h-6 w-6" />
                            </span>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-primary/70">Current Plan</p>
                                <p className="text-lg font-extrabold text-foreground">
                                    {subscription.plan_name}{subscription.billing_cycle ? ` · ${subscription.billing_cycle}` : ''}
                                    {subscription.price > 0 && <span className="ml-1 text-sm font-semibold text-muted-foreground">({formatPrice(subscription.price)}/mo)</span>}
                                </p>
                                <div className="mt-1.5 min-w-40">
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-primary/15">
                                        <div className="brand-gradient h-full rounded-full" style={{ width: `${usagePercent}%` }} />
                                    </div>
                                    <p className="mt-1 text-[11px] font-medium text-muted-foreground">
                                        {subscription.limit === null
                                            ? 'Unlimited listings'
                                            : `${subscription.usage ?? 0} of ${subscription.limit} listings used`}
                                        {subscription.status !== 'active' && ` · ${subscription.status}`}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <Link href={route('owner.subscriptions.index')} className={cn(buttonVariants())}>
                            Manage Plan <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </section>
            )}

            <section className="mt-8">
                <div className="mb-4 flex items-end justify-between gap-3">
                    <h3 className="text-lg font-bold tracking-tight">Quick Links</h3>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {quickLinks.map((link) => (
                        <Link
                            key={link.label}
                            href={link.href}
                            className="surface group flex items-center gap-3 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_40px_-16px_rgba(16,60,45,0.28)]"
                        >
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-muted text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                                <link.icon className="h-5 w-5" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-bold text-foreground">{link.label}</span>
                                <span className="block truncate text-xs text-muted-foreground">{link.description}</span>
                            </span>
                            <ArrowRight className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                        </Link>
                    ))}
                </div>
            </section>

            <div className="mt-9 flex items-end justify-between gap-3">
                <h3 className="text-lg font-bold tracking-tight">My Properties</h3>
                <span className="text-xs font-semibold text-muted-foreground">{properties.length} total</span>
            </div>

            {properties.length === 0 ? (
                <div className="mt-4">
                    <EmptyState
                        icon={HomeIcon}
                        title="No properties yet"
                        description="List your first property and start receiving enquiries directly from tenants."
                        action={<Link href={route('owner.properties.create')} className={cn(buttonVariants())}><HomeIcon className="h-4 w-4" /> List Your First Property</Link>}
                    />
                </div>
            ) : (
                <>
                    <div className="surface mt-3 hidden overflow-hidden md:block">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-muted/60 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                        <th className="px-5 py-3">Property</th>
                                        <th className="px-5 py-3">Type</th>
                                        <th className="px-5 py-3">Location</th>
                                        <th className="px-5 py-3">Rent</th>
                                        <th className="px-5 py-3">Applications</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {properties.map((property) => (
                                        <tr key={property.id} className="group transition-colors hover:bg-muted/40">
                                            <td className="px-5 py-3.5">
                                                <Link href={route('owner.properties.show', property.id)} className="flex items-center gap-3">
                                                    <div className="h-11 w-14 shrink-0 overflow-hidden rounded-lg">
                                                        <PropertyArt property={property} />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-1.5 font-semibold text-foreground">
                                                            <span className="truncate">{property.title}</span>
                                                            {property.verified && <BadgeCheck className="h-3.5 w-3.5 shrink-0 text-emerald-500" />}
                                                        </div>
                                                        <div className="text-[11px] text-muted-foreground">
                                                            {property.bedrooms > 0 ? `${property.bedrooms} bed · ` : ''}{property.bathrooms} bath{property.furnished ? ' · Furnished' : ''}
                                                        </div>
                                                    </div>
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3.5 capitalize text-muted-foreground">{typeLabels[property.property_type] || property.property_type}</td>
                                            <td className="px-5 py-3.5 text-muted-foreground">
                                                {[property.suburb, property.zone, property.city].filter(Boolean).join(', ') || '—'}
                                            </td>
                                            <td className="px-5 py-3.5 font-bold text-foreground">{formatPrice(property.price)}</td>
                                            <td className="px-5 py-3.5">
                                                <span className="inline-flex items-center gap-1.5 rounded-lg bg-muted px-2 py-1 text-xs font-bold text-foreground">
                                                    {property.applications_count ?? 0}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3.5"><StatusBadge status={property.status} /></td>
                                            <td className="px-5 py-3.5 text-right">
                                                <Link href={route('owner.properties.show', property.id)} className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'text-primary hover:text-primary')}>
                                                    Manage <ArrowRight className="h-3.5 w-3.5" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="mt-4 space-y-4 md:hidden">
                        {properties.map((property) => (
                            <Link key={property.id} href={route('owner.properties.show', property.id)} className="surface flex gap-4 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_40px_-16px_rgba(16,60,45,0.28)]">
                                <div className="h-24 w-24 shrink-0 overflow-hidden rounded-xl">
                                    <PropertyArt property={property} />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <h4 className="flex items-center gap-1.5 font-bold leading-snug text-foreground">
                                            <span className="truncate">{property.title}</span>
                                            {property.verified && <BadgeCheck className="h-3.5 w-3.5 shrink-0 text-emerald-500" />}
                                        </h4>
                                        <StatusBadge status={property.status} />
                                    </div>
                                    <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                        {[property.suburb, property.zone, property.city].filter(Boolean).join(', ') || 'Location on request'}
                                    </p>
                                    <div className="mt-2 text-sm font-bold text-foreground">{formatPrice(property.price)}<span className="font-semibold text-muted-foreground">/mo</span></div>
                                    <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                        <span className="capitalize">{typeLabels[property.property_type] || property.property_type}</span>
                                        {property.bedrooms > 0 && <span>· {property.bedrooms} bed</span>}
                                        <span>· {property.applications_count ?? 0} applications</span>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                </>
            )}
        </MainLayout>
    );
}