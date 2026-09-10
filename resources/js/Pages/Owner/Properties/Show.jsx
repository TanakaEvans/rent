import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    MapPin,
    BedDouble,
    Bath,
    Ruler,
    Armchair,
    CalendarClock,
    BadgeCheck,
    Star,
    Pencil,
    Trash2,
    FileText,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import PropertyArt from '@/Components/Shared/PropertyArt';
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

const statusLabels = {
    available: 'Available',
    reserved: 'Reserved',
    occupied: 'Occupied',
    unavailable: 'Unavailable',
};

const transitions = {
    available: ['reserved', 'unavailable'],
    reserved: ['occupied', 'available'],
    occupied: ['available', 'unavailable'],
    unavailable: ['available'],
};

const formatPrice = (value) => '$' + Number(value).toLocaleString();

const formatArea = (value, unit = 'm²') => (value ? `${Number(value).toLocaleString()} ${unit}` : null);

export default function PropertyShow({ auth, property, navigation = {} }) {
    const changeStatus = (target) => {
        router.put(route('owner.properties.status', property.id), { status: target }, {
            preserveScroll: true,
        });
    };

    const destroy = () => {
        if (confirm(`Delete "${property.title}"? This cannot be undone.`)) {
            router.delete(route('owner.properties.destroy', property.id));
        }
    };

    const location = [property.suburb, property.zone, property.city].filter(Boolean).join(', ');
    const areas = [
        formatArea(property.building_size),
        property.land_size ? `${Number(property.land_size).toLocaleString()} m² land` : null,
    ].filter(Boolean);

    return (
        <MainLayout title={property.title} auth={auth}>
            <Head title={property.title} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link href={route('owner.properties.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80">
                    <ArrowLeft className="h-4 w-4" /> Back to My Properties
                </Link>

                {navigation && (
                    <div className="flex items-center gap-2">
                        {navigation.prev ? (
                            <Link
                                href={route('owner.properties.show', navigation.prev)}
                                preserveScroll
                                className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-bold text-foreground shadow-sm transition-colors hover:border-primary/40 hover:text-primary"
                            >
                                <ChevronLeft className="h-3.5 w-3.5" /> Previous
                            </Link>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-muted-foreground opacity-50">
                                <ChevronLeft className="h-3.5 w-3.5" /> Previous
                            </span>
                        )}
                        {navigation.next ? (
                            <Link
                                href={route('owner.properties.show', navigation.next)}
                                preserveScroll
                                className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-bold text-foreground shadow-sm transition-colors hover:border-primary/40 hover:text-primary"
                            >
                                Next <ChevronRight className="h-3.5 w-3.5" />
                            </Link>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-muted-foreground opacity-50">
                                Next <ChevronRight className="h-3.5 w-3.5" />
                            </span>
                        )}
                    </div>
                )}
            </div>

            <div className="mb-6 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <div className="surface overflow-hidden">
                    <div className="relative h-72 sm:h-96">
                        <PropertyArt property={property} className="h-full" />
                        <span className="absolute left-4 top-4"><StatusBadge status={property.status} /></span>
                        {property.verified && (
                            <span className="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-emerald-500/90 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider text-white shadow-lg backdrop-blur-sm">
                                <BadgeCheck className="h-3.5 w-3.5" /> Verified
                            </span>
                        )}
                        {property.featured && (
                            <span className="absolute bottom-4 left-4 inline-flex items-center gap-1 rounded-full bg-amber-400 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider text-amber-950 shadow-lg">
                                <Star className="h-3.5 w-3.5 fill-current" /> Featured
                            </span>
                        )}
                    </div>
                </div>

                <div className="surface p-6">
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight text-foreground">{property.title}</h2>
                    {location && (
                        <p className="mt-1.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <MapPin className="h-4 w-4 shrink-0 text-emerald-500" /> {location}
                        </p>
                    )}

                    <div className="mt-5 flex flex-wrap items-baseline gap-2">
                        <span className="text-3xl font-extrabold tracking-tight text-foreground">{formatPrice(property.price)}</span>
                        <span className="text-sm font-medium text-muted-foreground">/ month</span>
                        {property.deposit > 0 && (
                            <span className="ml-1 text-xs font-semibold text-muted-foreground">Deposit {formatPrice(property.deposit)}</span>
                        )}
                    </div>

                    <div className="mt-6 grid grid-cols-2 gap-3">
                        <div className="rounded-xl bg-muted/60 p-3.5">
                            <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><BedDouble className="h-4 w-4 text-primary" /> Bedrooms</div>
                            <div className="mt-1 text-lg font-extrabold text-foreground">{property.bedrooms}</div>
                        </div>
                        <div className="rounded-xl bg-muted/60 p-3.5">
                            <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><Bath className="h-4 w-4 text-primary" /> Bathrooms</div>
                            <div className="mt-1 text-lg font-extrabold text-foreground">{property.bathrooms}</div>
                        </div>
                        {areas.map((area, i) => (
                            <div key={i} className="rounded-xl bg-muted/60 p-3.5">
                                <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><Ruler className="h-4 w-4 text-primary" /> {i === 0 ? 'Size' : 'Land'}</div>
                                <div className="mt-1 text-lg font-extrabold text-foreground">{area}</div>
                            </div>
                        ))}
                        <div className="rounded-xl bg-muted/60 p-3.5">
                            <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><Armchair className="h-4 w-4 text-primary" /> Furnishing</div>
                            <div className="mt-1 text-lg font-extrabold capitalize text-foreground">{property.furnished ? 'Furnished' : 'Unfurnished'}</div>
                        </div>
                        {property.available_from && (
                            <div className="rounded-xl bg-muted/60 p-3.5">
                                <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><CalendarClock className="h-4 w-4 text-primary" /> Available From</div>
                                <div className="mt-1 text-lg font-extrabold text-foreground">{new Date(property.available_from).toLocaleDateString()}</div>
                            </div>
                        )}
                    </div>

                    <div className="mt-6 flex flex-col gap-2">
                        <Link href={route('owner.properties.edit', property.id)} className={cn(buttonVariants())}>
                            <Pencil className="h-4 w-4" /> Edit Property
                        </Link>
                        <Link href={route('owner.viewing-slots.index', property.id)} className={cn(buttonVariants({ variant: 'outline' }), 'border-primary/30 text-primary hover:bg-primary hover:text-white')}>
                            <CalendarClock className="h-4 w-4" /> Viewing Slots
                        </Link>
                        <Button variant="ghost" onClick={destroy} className="text-rose-600 hover:bg-rose-50 hover:text-rose-700">
                            <Trash2 className="h-4 w-4" /> Delete Property
                        </Button>
                    </div>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <section className="surface p-6">
                    <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Description</h3>
                    <p className="text-[15px] leading-relaxed text-foreground">{property.description || 'No description provided.'}</p>

                    <h3 className="mb-3 mt-8 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Amenities</h3>
                    {property.amenities?.length ? (
                        <div className="flex flex-wrap gap-2">
                            {property.amenities.map((amenity) => (
                                <span key={amenity} className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-3 py-1.5 text-xs font-semibold capitalize text-foreground">
                                    {amenity.replace(/_/g, ' ')}
                                </span>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">No amenities listed.</p>
                    )}
                </section>

                <aside className="space-y-4">
                    {/* Status management */}
                    <div className="surface p-6">
                        <h3 className="mb-1 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Listing Status</h3>
                        <div className="mt-2 inline-block"><StatusBadge status={property.status} /></div>

                        {transitions[property.status]?.length > 0 && (
                            <>
                                <p className="mt-3 text-xs text-muted-foreground">Move this listing to:</p>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {transitions[property.status].map((target) => (
                                        <Button key={target} variant="outline" size="sm" onClick={() => changeStatus(target)}>
                                            {statusLabels[target] || target}
                                        </Button>
                                    ))}
                                </div>
                            </>
                        )}
                    </div>

                    {/* Change history */}
                    <div className="surface p-6">
                        <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Status History</h3>
                        {property.history?.length ? (
                            <ol className="relative ml-3 space-y-4 border-l border-border pl-5">
                                {property.history.map((entry) => (
                                    <li key={entry.id} className="relative">
                                        <span className="absolute -left-[26px] top-1 h-2.5 w-2.5 rounded-full border-2 border-background bg-primary" />
                                        <p className="text-sm font-semibold text-foreground">
                                            {statusLabels[entry.from_status] || entry.from_status}
                                            <span className="mx-1.5 text-muted-foreground">→</span>
                                            {statusLabels[entry.to_status] || entry.to_status}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {new Date(entry.created_at).toLocaleString()}
                                            {entry.changed_by && ` · ${entry.changed_by.name}`}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="text-sm text-muted-foreground">No status changes recorded yet.</p>
                        )}
                    </div>

                    <div className="surface p-6">
                        <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Applications</h3>
                        <div className="text-3xl font-extrabold text-foreground">{property.applications_count ?? 0}</div>
                        <p className="mt-1 text-xs text-muted-foreground">Tenant applications on this listing.</p>
                        <Link href={route('owner.dashboard')} className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'mt-4')}>
                            <FileText className="h-4 w-4" /> View Dashboard
                        </Link>
                    </div>

                    <div className="surface p-6">
                        <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Listing Facts</h3>
                        <dl className="space-y-2.5 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Type</dt>
                                <dd className="font-bold capitalize text-foreground">{typeLabels[property.property_type] || property.property_type}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Address</dt>
                                <dd className="text-right font-bold text-foreground">{property.address || '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Listed</dt>
                                <dd className="text-right font-bold text-foreground">{new Date(property.created_at).toLocaleDateString()}</dd>
                            </div>
                        </dl>
                    </div>
                </aside>
            </div>
        </MainLayout>
    );
}