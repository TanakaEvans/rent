import { useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Search,
    MapPin,
    BedDouble,
    Bath,
    Armchair,
    BadgeCheck,
    Star,
    ShieldCheck,
    Landmark,
    BadgeDollarSign,
    ArrowRight,
    Warehouse,
    Home,
    Building2,
    ChevronDown,
    SlidersHorizontal,
    Heart,
} from 'lucide-react';
import Brand from '@/Components/Shared/Brand';
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

const typeIcons = {
    house: Home,
    flat: Building2,
    townhouse: Building2,
    cottage: Home,
    room: Warehouse,
    commercial: Warehouse,
    land: Landmark,
};

const formatPrice = (value) => '$' + Number(value).toLocaleString();

const priceTiers = [
    ['500', '≤ $500'],
    ['1000', '≤ $1,000'],
    ['3000', '≤ $3,000'],
];

const bedroomOptions = ['0', '1', '2', '3', '4'];
const bathroomOptions = ['1', '2', '3', '4'];

export default function MarketplaceIndex({ properties = [], total = 0, pagination = {}, cities = [], zones = [], filters = {}, featured = [], favouriteIds = [], auth }) {
    const [favs, setFavs] = useState(favouriteIds);
    const [form, setForm] = useState({
        city: filters.city || '',
        zone: filters.zone || '',
        property_type: filters.property_type || '',
        bedrooms: filters.bedrooms != null ? String(filters.bedrooms) : '',
        bathrooms: filters.bathrooms != null ? String(filters.bathrooms) : '',
        min_price: filters.min_price != null ? String(filters.min_price) : '',
        max_price: filters.max_price != null ? String(filters.max_price) : '',
        furnished: filters.furnished != null ? (filters.furnished ? '1' : '0') : '',
        sort: filters.sort || 'newest',
        page: pagination.current_page || 1,
    });
    const [filtersOpen, setFiltersOpen] = useState(false);
    const priceTimer = useRef();

    const apply = (merge) => {
        const next = { ...form, ...merge };
        setForm(next);
        const { page, ...rest } = next;
        const params = Object.fromEntries(
            Object.entries(rest).filter(([, v]) => v !== '' && v !== null && v !== undefined)
        );
        if (page > 1) params.page = page;
        router.get(route('home'), params, {
            replace: true,
            preserveState: true,
            only: ['properties', 'total', 'pagination', 'cities', 'zones', 'featured', 'filters'],
        });
    };

    const liveSelect = (key) => (e) => apply({ [key]: e.target.value, page: 1 });

    const livePrice = (key) => (e) => {
        const value = e.target.value;
        setForm((f) => ({ ...f, [key]: value }));
        clearTimeout(priceTimer.current);
        priceTimer.current = setTimeout(() => apply({ [key]: value, page: 1 }), 350);
    };

    const resetFilters = () => {
        setForm({
            city: '',
            zone: '',
            property_type: '',
            bedrooms: '',
            bathrooms: '',
            min_price: '',
            max_price: '',
            furnished: '',
            sort: 'newest',
            page: 1,
        });
        router.get(route('home'), {}, { replace: true, preserveState: true });
    };

    const goToPage = (page) => {
        if (page < 1 || page > (pagination.last_page || 1)) return;
        apply({ page });
    };

    const isFavourited = (propertyId) => favs.includes(propertyId);

    const toggleFavourite = (propertyId) => {
        if (!auth?.user) {
            router.get(route('login'));
            return;
        }
        setFavs((current) => (current.includes(propertyId) ? current.filter((id) => id !== propertyId) : [...current, propertyId]));
        router.post(route('tenant.favourites.toggle', propertyId), {}, { preserveScroll: true });
    };

    const activeFilters = ['city', 'zone', 'property_type', 'bedrooms', 'bathrooms', 'min_price', 'max_price', 'furnished']
        .filter((key) => form[key] !== '' && form[key] != null).length;

    const activeTier = form.max_price != null && form.max_price !== '' ? String(form.max_price) : '';
    const hero = featured?.length ? featured : properties.slice(0, 3);

    const pageTotal = pagination.last_page || 1;
    const pageNumbers = [];
    for (let i = 1; i <= pageTotal; i++) pageNumbers.push(i);

    const PropertyBadges = ({ property }) => (
        <>
            {property.featured && (
                <span className="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-amber-400 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-amber-950 shadow-lg">
                    <Star className="h-3 w-3 fill-current" /> Featured
                </span>
            )}
            {property.verified && (
                <span className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-emerald-500/90 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-white shadow-lg backdrop-blur-sm">
                    <BadgeCheck className="h-3 w-3" /> Verified
                </span>
            )}
        </>
    );

    return (
        <>
            <Head title="Find a Home Without Agent Fees" />

            <div className="min-h-screen app-bg">
                <header className="dark-sidebar sticky top-0 z-40 border-b border-white/5">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <Link href={route('home')} aria-label="Dzimba home">
                            <Brand dark />
                        </Link>
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('login')}
                                className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'text-white hover:bg-white/10 hover:text-white')}
                            >
                                Sign In
                            </Link>
                            <Link href={route('login')} className={cn(buttonVariants({ size: 'sm' }), 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/40 hover:bg-emerald-400')}>
                                List Your Property
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </header>

                <section className="dark-sidebar relative overflow-hidden">
                    <span className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl animate-glow" />
                    <span className="pointer-events-none absolute -left-32 bottom-0 h-80 w-80 rounded-full bg-teal-400/15 blur-3xl" />
                    <div className="relative mx-auto max-w-7xl px-4 pb-16 pt-12 sm:px-6 sm:pt-16 lg:px-8 lg:pb-20 lg:pt-24">
                        <span className="kicker border-emerald-400/25 bg-emerald-400/10 text-emerald-300">
                            <ShieldCheck className="h-3.5 w-3.5" /> Verified owners · Direct rent — no agents
                        </span>
                        <h1 className="mt-5 max-w-3xl text-balance text-4xl font-extrabold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">
                            Rent directly from owners.{' '}
                            <span className="bg-gradient-to-r from-emerald-300 via-teal-300 to-cyan-300 bg-clip-text text-transparent">No agents. No commissions.</span>
                        </h1>
                        <p className="mt-5 max-w-2xl text-balance text-base leading-relaxed text-white/70 sm:text-lg">
                            Browse verified homes, flats and townhouses listed directly by owners. Apply in minutes, view in person, and pay an affordable subscription — never another agent commission.
                        </p>
                        <div className="mt-7 flex flex-wrap gap-2.5">
                            {[
                                { icon: BadgeCheck, text: 'Verified Properties' },
                                { icon: Home, text: `${properties.length}+ Homes Available` },
                                { icon: BadgeDollarSign, text: 'No Agent Fees' },
                                { icon: ShieldCheck, text: 'Direct Owner Contact' },
                            ].map(({ icon: Icon, text }) => (
                                <span key={text} className="inline-flex items-center gap-2 rounded-full border border-white/12 bg-white/8 px-3.5 py-1.5 text-xs font-semibold text-white/85 backdrop-blur-sm">
                                    <Icon className="h-3.5 w-3.5 text-emerald-300" />
                                    {text}
                                </span>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="mx-auto -mt-7 max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="surface relative z-10 overflow-hidden !rounded-2xl p-4 sm:p-5">
                        <button
                            type="button"
                            onClick={() => setFiltersOpen((v) => !v)}
                            className="mb-3 flex w-full items-center justify-between gap-2 rounded-xl bg-muted px-4 py-3 text-sm font-bold text-foreground lg:hidden"
                        >
                            <span className="inline-flex items-center gap-2">
                                <SlidersHorizontal className="h-4 w-4 text-primary" /> Filters
                                {activeFilters > 0 && (
                                    <span className="grid h-5 min-w-5 place-items-center rounded-full brand-gradient px-1.5 text-[10px] font-extrabold text-white">{activeFilters}</span>
                                )}
                            </span>
                            <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', filtersOpen && 'rotate-180')} />
                        </button>

                        <div className={cn('grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5', !filtersOpen && 'hidden lg:grid')}>
                            <div>
                                <label className="field-label">City</label>
                                <select value={form.city} onChange={liveSelect('city')} className="field">
                                    <option value="">Any City</option>
                                    {cities.map((city) => (
                                        <option key={city} value={city}>{city}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="field-label">Zone</label>
                                <select value={form.zone} onChange={liveSelect('zone')} className="field">
                                    <option value="">Any Zone</option>
                                    {zones.map((zone) => (
                                        <option key={zone} value={zone}>{zone}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="field-label">Property Type</label>
                                <select value={form.property_type} onChange={liveSelect('property_type')} className="field">
                                    <option value="">Any Type</option>
                                    {Object.entries(typeLabels).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="field-label">Bedrooms</label>
                                <select value={form.bedrooms} onChange={liveSelect('bedrooms')} className="field">
                                    <option value="">Any</option>
                                    <option value="0">Studio / 0</option>
                                    {bedroomOptions.filter((n) => n !== '0').map((n) => (
                                        <option key={n} value={n}>{n}+</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="field-label">Bathrooms</label>
                                <select value={form.bathrooms} onChange={liveSelect('bathrooms')} className="field">
                                    <option value="">Any</option>
                                    {bathroomOptions.map((n) => (
                                        <option key={n} value={n}>{n}+</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="field-label">Furnishing</label>
                                <select value={form.furnished} onChange={liveSelect('furnished')} className="field">
                                    <option value="">Any</option>
                                    <option value="1">Furnished</option>
                                    <option value="0">Unfurnished</option>
                                </select>
                            </div>
                            <div>
                                <label className="field-label">Min Rent (USD)</label>
                                <input type="number" min="0" placeholder="e.g. 300" value={form.min_price} onChange={livePrice('min_price')} className="field" />
                            </div>
                            <div>
                                <label className="field-label">Max Rent (USD)</label>
                                <input type="number" min="0" placeholder="e.g. 800" value={form.max_price} onChange={livePrice('max_price')} className="field" />
                            </div>
                            <div>
                                <label className="field-label">Sort</label>
                                <select value={form.sort} onChange={liveSelect('sort')} className="field">
                                    <option value="newest">Newest First</option>
                                    <option value="price_asc">Price: Low to High</option>
                                    <option value="price_desc">Price: High to Low</option>
                                </select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button type="button" variant="ghost" onClick={resetFilters} className="h-11 flex-1">
                                    Reset
                                </Button>
                            </div>
                            <div className="sm:col-span-2 lg:col-span-5">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Quick budget</span>
                                    {priceTiers.map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => apply({ max_price: activeTier === value ? '' : value, page: 1 })}
                                            className={cn(
                                                'rounded-full border px-3.5 py-1.5 text-xs font-bold transition-colors',
                                                activeTier === value
                                                    ? 'border-primary bg-primary text-white'
                                                    : 'border-border bg-muted/50 text-muted-foreground hover:border-primary/40 hover:text-primary'
                                            )}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                    {activeTier !== '' && (
                                        <button
                                            type="button"
                                            onClick={() => apply({ max_price: '', page: 1 })}
                                            className="rounded-full border border-dashed border-border px-3.5 py-1.5 text-xs font-bold text-muted-foreground hover:border-primary/40 hover:text-primary"
                                        >
                                            Any
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {hero.length > 0 && (
                    <section className="mx-auto mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="overflow-hidden rounded-3xl border border-white/10 bg-slate-950/90 shadow-2xl">
                            <div className="grid lg:grid-cols-2">
                                <div className="relative min-h-56 lg:min-h-full">
                                    <PropertyArt property={hero[0]} className="h-full" />
                                    <span className="absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-900 shadow">
                                        <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" /> Spotlight pick
                                    </span>
                                    <span className="absolute bottom-4 left-4 inline-flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1.5 text-[11px] font-bold text-white backdrop-blur">
                                        <BadgeCheck className="h-3.5 w-3.5 text-emerald-300" /> {hero[0].title}
                                    </span>
                                </div>
                                <div className="relative z-10 p-7 text-white sm:p-10">
                                    <span className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-300">Featured this week</span>
                                    <h2 className="mt-2 text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">
                                        {hero[0].title}
                                    </h2>
                                    <p className="mt-2 flex items-center gap-1.5 text-sm text-white/65">
                                        <MapPin className="h-4 w-4 text-emerald-400" />
                                        {[hero[0].suburb, hero[0].zone, hero[0].city].filter(Boolean).join(', ')}
                                    </p>
                                    <div className="mt-4 flex flex-wrap items-baseline gap-2">
                                        <span className="text-4xl font-extrabold tracking-tight">{formatPrice(hero[0].price)}</span>
                                        <span className="text-sm font-medium text-white/60">/ month</span>
                                    </div>
                                    <div className="mt-5 flex flex-wrap items-center gap-4 text-sm text-white/80">
                                        <span className="inline-flex items-center gap-1.5"><BedDouble className="h-4 w-4 text-emerald-300" /> {hero[0].bedrooms} bed</span>
                                        <span className="inline-flex items-center gap-1.5"><Bath className="h-4 w-4 text-emerald-300" /> {hero[0].bathrooms} bath</span>
                                        <span className="inline-flex items-center gap-1.5"><Armchair className="h-4 w-4 text-emerald-300" /> {hero[0].furnished ? 'Furnished' : 'Unfurnished'}</span>
                                    </div>
                                    <div className="mt-7 flex flex-wrap gap-3">
                                        <Link href={route('login')} className={cn(buttonVariants(), 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/40 hover:bg-emerald-400')}>
                                            Enquire Now <ArrowRight className="h-4 w-4" />
                                        </Link>
                                        <Link href={route('property.show', hero[0].id)} className={cn(buttonVariants({ variant: 'outline' }), 'border-white/25 bg-white/5 text-white hover:bg-white/10 hover:text-white')}>
                                            View Details
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-14 lg:px-8">
                    <div className="section-title-wrap mb-7">
                        <span className="section-title-bar" />
                        <h2 className="section-title-text">Available Properties</h2>
                        <span className="mt-1 text-xs font-semibold text-muted-foreground">
                            {total} listing{total !== 1 ? 's' : ''} live
                        </span>
                    </div>

                    {properties.length === 0 ? (
                        <EmptyState
                            icon={Search}
                            title="No properties match your filters"
                            description="Try adjusting your search criteria, or check back soon — new homes are listed every week."
                            action={
                                <Button onClick={resetFilters}>View All Properties</Button>
                            }
                        />
                    ) : (
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {properties.map((property) => {
                                const TypeIcon = typeIcons[property.property_type] || Home;
                                return (
                                    <article
                                        key={property.id}
                                        className="group surface flex flex-col overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_24px_60px_-24px_rgba(16,60,45,0.35)] hover:ring-1 hover:ring-primary/20"
                                    >
                                        <div className="relative h-52 overflow-hidden sm:h-56">
                                            <Link href={route('property.show', property.id)}>
                                                <PropertyArt property={property} className="transition-transform duration-500 group-hover:scale-105" />
                                            </Link>
                                            <PropertyBadges property={property} />
                                        </div>

                                        <div className="flex flex-1 flex-col p-5">
                                            <div className="flex items-start justify-between gap-3">
                                                <h3 className="text-balance text-lg font-bold tracking-tight text-foreground transition-colors group-hover:text-primary">
                                                    <Link href={route('property.show', property.id)}>{property.title}</Link>
                                                </h3>
                                            </div>
                                            <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                                                <MapPin className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                                {[property.suburb, property.zone, property.city].filter(Boolean).join(', ') || 'Location on request'}
                                            </p>

                                            <div className="mt-4 flex flex-wrap gap-1.5">
                                                <span className="inline-flex items-center gap-1 rounded-lg border border-border bg-muted px-2 py-1 text-[11px] font-semibold text-foreground">
                                                    <TypeIcon className="h-3 w-3 text-primary" /> {typeLabels[property.property_type] || property.property_type}
                                                </span>
                                                {property.bedrooms > 0 && (
                                                    <span className="inline-flex items-center gap-1 rounded-lg border border-border bg-muted px-2 py-1 text-[11px] font-semibold text-foreground">
                                                        <BedDouble className="h-3 w-3 text-primary" /> {property.bedrooms} bed
                                                    </span>
                                                )}
                                                <span className="inline-flex items-center gap-1 rounded-lg border border-border bg-muted px-2 py-1 text-[11px] font-semibold text-foreground">
                                                    <Bath className="h-3 w-3 text-primary" /> {property.bathrooms} bath
                                                </span>
                                                <span className="inline-flex items-center gap-1 rounded-lg border border-border bg-muted px-2 py-1 text-[11px] font-semibold text-foreground">
                                                    <Armchair className="h-3 w-3 text-primary" /> {property.furnished ? 'Furnished' : 'Unfurnished'}
                                                </span>
                                            </div>

                                            <div className="mt-auto flex items-end justify-between gap-3 pt-5">
                                                <div>
                                                    <div className="text-2xl font-extrabold tracking-tight text-foreground">
                                                        {formatPrice(property.price)}
                                                        <span className="text-xs font-semibold text-muted-foreground">/mo</span>
                                                    </div>
                                                    {property.deposit > 0 && (
                                                        <div className="text-[11px] font-medium text-muted-foreground">Deposit {formatPrice(property.deposit)}</div>
                                                    )}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => toggleFavourite(property.id)}
                                                    aria-pressed={isFavourited(property.id)}
                                                    aria-label={isFavourited(property.id) ? 'Remove from favourites' : 'Save to favourites'}
                                                    className={cn(
                                                        'grid h-9 w-9 place-items-center rounded-lg border transition-colors',
                                                        isFavourited(property.id)
                                                            ? 'border-rose-200 bg-rose-50 text-rose-500'
                                                            : 'border-border text-muted-foreground hover:border-rose-200 hover:bg-rose-50 hover:text-rose-500'
                                                    )}
                                                >
                                                    <Heart className={cn('h-4 w-4', isFavourited(property.id) && 'fill-rose-500')} />
                                                </button>
                                                <Link
                                                    href={route('login')}
                                                    className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'border-primary/30 text-primary hover:bg-primary hover:text-white')}
                                                >
                                                    Contact Owner <ArrowRight className="h-3.5 w-3.5" />
                                                </Link>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}

                    {pageTotal > 1 && (
                        <nav className="mt-10 flex items-center justify-between gap-3">
                            <p className="text-xs font-semibold text-muted-foreground">
                                Page {pagination.current_page} of {pageTotal} · {total} listings
                            </p>
                            <div className="flex items-center gap-1.5">
                                <Button type="button" variant="outline" size="sm" disabled={pagination.current_page <= 1} onClick={() => goToPage(pagination.current_page - 1)}>
                                    Previous
                                </Button>
                                {pageNumbers.map((num) => (
                                    <Button
                                        key={num}
                                        type="button"
                                        variant={num === pagination.current_page ? 'default' : 'outline'}
                                        size="sm"
                                        className={cn(num === pagination.current_page && 'pointer-events-none')}
                                        onClick={() => goToPage(num)}
                                    >
                                        {num}
                                    </Button>
                                ))}
                                <Button type="button" variant="outline" size="sm" disabled={pagination.current_page >= pageTotal} onClick={() => goToPage(pagination.current_page + 1)}>
                                    Next
                                </Button>
                            </div>
                        </nav>
                    )}
                </section>

                <section className="border-y border-border bg-card mx-0 px-4 py-14 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-7xl">
                        <div className="mx-auto mb-10 max-w-xl text-center">
                            <h2 className="text-balance text-3xl font-extrabold tracking-tight">Renting without agents, step by step</h2>
                            <p className="mt-2 text-sm text-muted-foreground">Direct owners. Direct tenants. The platform gives you the services agents used to gatekeep — without the commission.</p>
                        </div>
                        <div className="grid gap-6 md:grid-cols-3">
                            {[
                                { icon: Home, title: 'Owners list directly', copy: 'Upload your property, photos and details in minutes — no agent required.', tile: 'from-emerald-500/15 to-emerald-500/5 text-emerald-600' },
                                { icon: Search, title: 'Tenants search freely', copy: 'Search, filter and save available homes. Message the owner and apply in minutes.', tile: 'from-teal-500/15 to-teal-500/5 text-teal-600' },
                                { icon: BadgeDollarSign, title: 'Affordable subscriptions', copy: 'Owners pay a small monthly fee instead of handing an agent a huge commission.', tile: 'from-amber-500/15 to-amber-500/5 text-amber-600' },
                            ].map(({ icon: Icon, title, copy, tile }) => (
                                <div key={title} className="surface p-7 transition-all duration-300 hover:-translate-y-1">
                                    <span className={cn('grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br ring-1 ring-inset ring-black/5', tile)}>
                                        <Icon className="h-6 w-6" strokeWidth={1.9} />
                                    </span>
                                    <h3 className="mt-5 text-lg font-bold tracking-tight">{title}</h3>
                                    <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{copy}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <footer className="dark-sidebar">
                    <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-4 py-10 sm:px-6 md:flex-row lg:px-8">
                        <Brand dark />
                        <p className="text-sm font-medium text-white/60">Rent directly. Live simply.</p>
                        <div className="flex gap-6 text-sm font-semibold text-white/70">
                            <Link href={route('login')} className="transition-colors hover:text-emerald-300">Sign In</Link>
                            <Link href={route('login')} className="transition-colors hover:text-emerald-300">For Owners</Link>
                            <Link href={route('login')} className="transition-colors hover:text-emerald-300">For Tenants</Link>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}