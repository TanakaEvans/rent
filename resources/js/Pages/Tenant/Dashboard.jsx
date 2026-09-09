import { Head, Link } from '@inertiajs/react';
import { Heart, FileText, Hourglass, BadgeCheck, MapPin, CalendarDays, Mail, BedDouble, Bath, Armchair, MessageSquareText } from 'lucide-react';
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
const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '';

export default function TenantDashboard({ stats = {}, favourites = [], applications = [] }) {
    const statCards = [
        { key: 'enquiries', label: 'Enquiries', value: stats.enquiries ?? 0, icon: MessageSquareText, tone: 'sky' },
        { key: 'favourites', label: 'Favourite Homes', value: stats.favourites ?? 0, icon: Heart, tone: 'rose' },
        { key: 'applications', label: 'Applications', value: stats.applications ?? 0, icon: FileText, tone: 'emerald' },
        { key: 'pending', label: 'Under Review', value: stats.pending ?? 0, icon: Hourglass, tone: 'amber' },
        { key: 'approved', label: 'Approved', value: stats.approved ?? 0, icon: BadgeCheck, tone: 'teal' },
    ];

    return (
        <MainLayout title="Tenant Dashboard">
            <Head title="Tenant Dashboard" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Find Your Next Home</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Track your enquiries and applications, and keep an eye on the homes you love.</p>
                </div>
                <Link href={route('tenant.enquiries.index')} className={cn(buttonVariants({ variant: 'outline' }), 'border-primary/30 text-primary hover:bg-primary hover:text-white')}>
                    <MessageSquareText className="h-4 w-4" /> My Enquiries
                </Link>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {statCards.map((card) => (
                    <StatCard key={card.key} {...card} />
                ))}
            </div>

            <section className="mt-9">
                <div className="mb-4 flex items-end justify-between gap-3">
                    <h3 className="text-lg font-bold tracking-tight">My Applications</h3>
                    <span className="text-xs font-semibold text-muted-foreground">{applications.length} total</span>
                </div>

                {applications.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="No applications yet"
                        description="Apply to any available home directly — no agents, no fees."
                    />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {applications.map((application) => (
                            <article key={application.id} className="surface flex gap-4 p-4">
                                <div className="h-24 w-24 shrink-0 overflow-hidden rounded-xl">
                                    <PropertyArt property={application.property || {}} />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <h4 className="font-bold leading-snug text-foreground">{application?.property?.title || 'Property'}</h4>
                                        <StatusBadge status={application.status} />
                                    </div>
                                    <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                        {[application?.property?.suburb, application?.property?.city].filter(Boolean).join(', ') || 'Location on request'}
                                    </p>
                                    <div className="mt-1 text-sm font-bold text-foreground">
                                        {formatPrice(application?.property?.price)}
                                        <span className="font-semibold text-muted-foreground">/mo</span>
                                    </div>
                                    {application.message && (
                                        <p className="mt-2 line-clamp-2 text-xs leading-relaxed text-muted-foreground">“{application.message}”</p>
                                    )}
                                    <p className="mt-2 flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                                        <CalendarDays className="h-3 w-3" /> Applied {formatDate(application?.created_at)}
                                    </p>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>

            <section className="mt-10">
                <div className="mb-4 flex items-end justify-between gap-3">
                    <h3 className="text-lg font-bold tracking-tight">Favourite Homes</h3>
                    <span className="text-xs font-semibold text-muted-foreground">{favourites.length} saved</span>
                </div>

                {favourites.length === 0 ? (
                    <EmptyState
                        icon={Heart}
                        title="No favourites yet"
                        description="Save homes you love on the marketplace and they will appear here."
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {favourites.map((property) => (
                            <article key={property.id} className="group surface flex flex-col overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_24px_60px_-24px_rgba(16,60,45,0.35)]">
                                <div className="relative h-40 overflow-hidden">
                                    <PropertyArt property={property} className="transition-transform duration-500 group-hover:scale-105" />
                                    {property.verified && (
                                        <span className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-emerald-500/90 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider text-white shadow">
                                            <BadgeCheck className="h-3 w-3" /> Verified
                                        </span>
                                    )}
                                </div>
                                <div className="flex flex-1 flex-col p-4">
                                    <h4 className="font-bold leading-snug text-foreground">{property.title}</h4>
                                    <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                        {[property.suburb, property.city].filter(Boolean).join(', ') || 'Location on request'}
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                        <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-0.5 text-[10px] font-semibold capitalize text-foreground">
                                            {typeLabels[property.property_type] || property.property_type}
                                        </span>
                                        {property.bedrooms > 0 && (
                                            <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-0.5 text-[10px] font-semibold text-foreground">
                                                <BedDouble className="h-3 w-3 text-primary" /> {property.bedrooms} bed
                                            </span>
                                        )}
                                        <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-0.5 text-[10px] font-semibold text-foreground">
                                            <Bath className="h-3 w-3 text-primary" /> {property.bathrooms} bath
                                        </span>
                                        <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-0.5 text-[10px] font-semibold text-foreground">
                                            <Armchair className="h-3 w-3 text-primary" /> {property.furnished ? 'Furnished' : 'Unfurnished'}
                                        </span>
                                    </div>
                                    <div className="mt-auto flex items-end justify-between gap-3 pt-4">
                                        <div className="text-lg font-extrabold tracking-tight">
                                            {formatPrice(property.price)}
                                            <span className="text-xs font-semibold text-muted-foreground">/mo</span>
                                        </div>
                                        <Button size="sm">Enquire</Button>
                                    </div>
                                    {property?.owner?.name && (
                                        <p className="mt-1 flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                                            <Mail className="h-3 w-3" /> Listed by {property.owner.name}
                                        </p>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>
        </MainLayout>
    );
}