import { Head, Link, router } from '@inertiajs/react';
import { Plus, MapPin, Pencil, Trash2, Home as HomeIcon, Building2, BadgeCheck, ArrowRight } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
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

export default function PropertiesIndex({ auth, properties = [] }) {
    const destroy = (property) => {
        if (confirm(`Delete "${property.title}"? This cannot be undone.`)) {
            router.delete(route('owner.properties.destroy', property.id));
        }
    };

    return (
        <MainLayout title="My Properties" auth={auth}>
            <Head title="My Properties" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Properties</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Create, edit and manage your rental listings.</p>
                </div>
                <Link href={route('owner.properties.create')} className={cn(buttonVariants())}>
                    <Plus className="h-4 w-4" /> List a New Property
                </Link>
            </div>

            {properties.length === 0 ? (
                <EmptyState
                    icon={HomeIcon}
                    title="No properties yet"
                    description="List your first property and start receiving enquiries directly from tenants."
                    action={
                        <Link href={route('owner.properties.create')} className={cn(buttonVariants())}>
                            <Plus className="h-4 w-4" /> List Your First Property
                        </Link>
                    }
                />
            ) : (
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {properties.map((property) => (
                        <article key={property.id} className="surface group flex flex-col overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_24px_60px_-24px_rgba(16,60,45,0.35)] hover:ring-1 hover:ring-primary/20">
                            <div className="relative h-44 overflow-hidden">
                                <PropertyArt property={property} className="transition-transform duration-500 group-hover:scale-105" />
                                <span className="absolute left-3 top-3">
                                    <StatusBadge status={property.status} />
                                </span>
                                {property.verified && (
                                    <span className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-emerald-500/90 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-white shadow-lg backdrop-blur-sm">
                                        <BadgeCheck className="h-3 w-3" /> Verified
                                    </span>
                                )}
                            </div>

                            <div className="flex flex-1 flex-col p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <h3 className="text-balance text-base font-bold leading-snug tracking-tight text-foreground">
                                        {property.title}
                                    </h3>
                                </div>
                                <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                    {[property.suburb, property.zone, property.city].filter(Boolean).join(', ') || 'Location on request'}
                                </p>

                                <div className="mt-2 text-sm font-bold text-foreground">
                                    {formatPrice(property.price)}<span className="font-semibold text-muted-foreground">/mo</span>
                                </div>

                                <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                    <span className="capitalize">{typeLabels[property.property_type] || property.property_type}</span>
                                    {property.bedrooms > 0 && <span>· {property.bedrooms} bed</span>}
                                    <span>· {property.applications_count ?? 0} applications</span>
                                </div>

                                <div className="mt-auto flex items-center gap-2 pt-5">
                                    <Link href={route('owner.properties.show', property.id)} className={cn(buttonVariants({ size: 'sm' }), 'flex-1')}>
                                        View <ArrowRight className="h-3.5 w-3.5" />
                                    </Link>
                                    <Link href={route('owner.properties.edit', property.id)} className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}>
                                        <Pencil className="h-3.5 w-3.5" /> Edit
                                    </Link>
                                    <Button variant="ghost" size="sm" onClick={() => destroy(property)} className="text-rose-600 hover:bg-rose-50 hover:text-rose-700">
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}