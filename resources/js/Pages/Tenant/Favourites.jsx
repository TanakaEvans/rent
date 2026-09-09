import { Head, Link, router } from '@inertiajs/react';
import { Heart, Trash2, MapPin, BedDouble, Bath, Armchair, BadgeCheck, Mail } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import EmptyState from '@/Components/Shared/EmptyState';
import { Button } from '@/Components/ui/button';
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

const typeTones = {
    house: 'text-emerald-600 bg-emerald-500/10',
    flat: 'text-teal-600 bg-teal-500/10',
    townhouse: 'text-sky-600 bg-sky-500/10',
    cottage: 'text-amber-600 bg-amber-500/10',
    room: 'text-violet-600 bg-violet-500/10',
    commercial: 'text-slate-600 bg-slate-500/10',
    land: 'text-lime-600 bg-lime-500/10',
};

const formatPrice = (value) => '$' + Number(value).toLocaleString();

export default function TenantFavourites({ favourites = [], stats = {} }) {
    const remove = (propertyId) => {
        router.post(route('tenant.favourites.toggle', propertyId), {}, { preserveScroll: true });
    };

    return (
        <MainLayout title="My Favourites">
            <Head title="My Favourites" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Favourites</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Homes you have shortlisted — <span className="font-semibold text-foreground">{stats.available ?? 0}</span> still available now.
                    </p>
                </div>
                <span className="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-600">
                    <Heart className="h-3.5 w-3.5 fill-rose-500" /> {stats.total ?? 0} saved
                </span>
            </div>

            {favourites.length === 0 ? (
                <EmptyState
                    icon={Heart}
                    title="No favourites yet"
                    description="Tap the heart on any marketplace listing or property page to save it here."
                />
            ) : (
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {favourites.map((property) => {
                        const available = property.status === 'available';
                        return (
                            <article
                                key={property.id}
                                className={cn(
                                    'group surface flex flex-col overflow-hidden transition-all duration-300',
                                    available
                                        ? 'hover:-translate-y-1 hover:shadow-[0_24px_60px_-24px_rgba(16,60,45,0.35)]'
                                        : 'opacity-70 saturate-50'
                                )}
                            >
                                <Link href={available ? route('property.show', property.id) : '#'}>
                                    <div className="relative h-40 overflow-hidden">
                                        <PropertyArt property={property} className="transition-transform duration-500 group-hover:scale-105" />
                                        <span className="absolute left-3 top-3 inline-flex items-center rounded-full bg-black/50 px-2.5 py-1 text-[10px] font-extrabold capitalize tracking-wide text-white backdrop-blur-sm">
                                            {available ? 'Available' : 'Unavailable'}
                                        </span>
                                        {property.verified && (
                                            <span className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-emerald-500/90 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider text-white shadow">
                                                <BadgeCheck className="h-3 w-3" /> Verified
                                            </span>
                                        )}
                                    </div>
                                </Link>
                                <div className="flex flex-1 flex-col p-4">
                                    <h4 className="font-bold leading-snug text-foreground">{property.title}</h4>
                                    <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                        {[property.suburb, property.city].filter(Boolean).join(', ') || 'Location on request'}
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                        <span className={cn('inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold capitalize', typeTones[property.property_type] || 'bg-muted text-foreground')}>
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
                                        <div className="flex items-center gap-2">
                                            {available && (
                                                <Button asChild size="sm">
                                                    <Link href={route('property.show', property.id)}>View</Link>
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => remove(property.id)}
                                                className="text-muted-foreground hover:bg-rose-50 hover:text-rose-500"
                                                aria-label={`Remove ${property.title} from favourites`}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    {property?.owner?.name && (
                                        <p className="mt-1 flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                                            <Mail className="h-3 w-3" /> Listed by {property.owner.name}
                                        </p>
                                    )}
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}
        </MainLayout>
    );
}