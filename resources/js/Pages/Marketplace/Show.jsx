import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {
    ArrowLeft,
    LocateFixed,
    Navigation,
    ExternalLink,
    MapPin,
    BedDouble,
    Bath,
    Ruler,
    Armchair,
    BadgeCheck,
    Star,
    CalendarClock,
    Mail,
    ShieldCheck,
    ShieldAlert,
    Send,
    ClipboardCheck,
    Heart,
    Flag,
    ChevronLeft,
    ChevronRight,
    UserCheck,
    Clock,
    DollarSign,
    KeyRound,
    Sparkles,
    History,
    Building2,
    Users,
    Undo2,
    X,
    MessageCircle,
} from 'lucide-react';
import Brand from '@/Components/Shared/Brand';
import ViewingBooker from '@/Components/Shared/ViewingBooker';
import MapStyleToggle from '@/Components/Shared/MapStyleToggle';
import { createBaseLayers, createMap, hasCoordinates, homePinIcon } from '@/lib/map';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import OwnerTrustBadge from '@/Components/Shared/OwnerTrustBadge';
import ChatWidget from '@/Components/Shared/ChatWidget';
import { buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import { dashboardRouteFor } from '@/lib/roles';
import {
    TYPE_LABELS as typeLabels,
    formatPrice,
    paymentPeriod,
    yesNoOrDash,
    optionLabel,
    PARKING_TYPES,
    SECURITY_TYPES,
    PREFERRED_TENANTS,
    ENTRANCE_TYPES,
    BATHROOM_TYPES,
} from '@/lib/listing';

const reportCategoryLabels = {
    suspicious_listing: 'Suspicious listing',
    incorrect_information: 'Incorrect information',
    duplicate: 'Duplicate listing',
    wrong_price: 'Wrong price',
    fraud_concern: 'Fraud concern',
    already_rented: 'Already rented',
    inappropriate_content: 'Inappropriate content',
};

const availabilityOf = (property) => {
    if (property.available_from && new Date(property.available_from).getTime() > Date.now()) {
        return `Available from ${new Date(property.available_from).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`;
    }
    return 'Available now';
};

function MapEmbed({ property, exact, radius }) {
    const mountRef = useRef(null);
    const mapRef = useRef(null);
    const layersRef = useRef(null);
    const [style, setStyle] = useState('map');

    useEffect(() => {
        const lat = Number(property.latitude);
        const lng = Number(property.longitude);
        if (!mountRef.current || !hasCoordinates(property)) return undefined;

        const map = createMap(mountRef.current, { scrollWheelZoom: false });
        layersRef.current = createBaseLayers();
        layersRef.current.map.addTo(map);
        mapRef.current = map;

        if (exact) {
            L.marker([lat, lng], { icon: homePinIcon(), title: property.title }).addTo(map);
            map.setView([lat, lng], 16);
        } else {
            // inDrive-style privacy: a shaded circle covering the approximate
            // area, never the exact pin, until the viewer is entitled.
            L.circle([lat, lng], {
                radius: radius || 500,
                color: '#059669',
                weight: 1.5,
                fillColor: '#10b981',
                fillOpacity: 0.15,
            }).addTo(map);
            map.setView([lat, lng], 15);
        }
        map.once('focus', () => map.scrollWheelZoom.enable());

        return () => {
            map.remove();
            mapRef.current = null;
        };
    }, [property.id, property.latitude, property.longitude, property.title, exact, radius]);

    useEffect(() => {
        const map = mapRef.current;
        const layers = layersRef.current;
        if (!map || !layers) return;
        Object.entries(layers).forEach(([key, layer]) => {
            if (key === style) layer.addTo(map);
            else map.removeLayer(layer);
        });
    }, [style]);

    const recenter = () => mapRef.current?.setView([Number(property.latitude), Number(property.longitude)], 16);

    return (
        <div className="dz-map relative isolate h-80 w-full sm:h-96">
            <div ref={mountRef} className="h-full w-full" aria-label="Property location map" />
            <MapStyleToggle value={style} onChange={setStyle} />
            <button type="button" onClick={recenter} className="absolute right-3 top-3 z-[1000] inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-900 shadow-[0_2px_10px_rgba(0,0,0,0.14)] hover:bg-slate-50">
                <LocateFixed className="h-3.5 w-3.5" /> Recenter
            </button>
        </div>
    );
}

function SimilarCard({ property }) {
    const images = property.images?.length ? property.images : [];
    return (
        <Link
            href={route('property.show', property.id)}
            className="w-[240px] shrink-0 snap-start overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-[0_22px_50px_-25px_rgba(15,23,42,.4)]"
        >
            <div className="relative h-36 bg-slate-100">
                {images.length > 0 ? (
                    <img src={images[0].path} alt={property.title} className="h-full w-full object-cover" />
                ) : (
                    <PropertyArt property={property} className="h-full w-full" />
                )}
                {property.featured && (
                    <span className="absolute left-2.5 top-2.5 inline-flex items-center gap-1 rounded-full bg-amber-400 px-2 py-1 text-[9px] font-black uppercase tracking-[.12em] text-amber-950">
                        <Star className="h-3 w-3 fill-current" /> Featured
                    </span>
                )}
            </div>
            <div className="p-4">
                <div className="text-lg font-black tracking-tight text-slate-950">
                    {formatPrice(property.price, property.currency)}<span className="ml-1 text-[11px] font-bold text-slate-400">{paymentPeriod(property.payment_terms)}</span>
                </div>
                <h4 className="mt-1 truncate text-sm font-bold text-slate-900">{property.title}</h4>
                <p className="mt-0.5 truncate text-xs font-medium text-slate-400">
                    {[property.suburb, property.zone, property.city].filter(Boolean).join(', ') || typeLabels[property.property_type]}
                </p>
                <div className="mt-2 flex items-center justify-between">
                    <span className={cn('inline-flex items-center gap-1.5 text-[11px] font-bold', property.verified ? 'text-emerald-600' : 'text-slate-400')}>
                        {property.verified ? <BadgeCheck className="h-3.5 w-3.5" /> : <BadgeCheck className="h-3.5 w-3.5 opacity-30" />}
                        {property.verified ? 'Verified' : 'Listing'}
                    </span>
                    <OwnerTrustBadge owner={property.owner} className="px-2 py-0.5" />
                </div>
            </div>
        </Link>
    );
}

function ResultRail({ title, icon: Icon, items }) {
    if (!items?.length) return null;
    return (
        <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-600">
                <Icon className="mr-1 inline h-3.5 w-3.5" /> {title}
            </span>
            <h2 className="mt-2 text-2xl font-black tracking-[-.025em] text-slate-950 sm:text-3xl">{title}</h2>
            <div className="mt-6 flex snap-x gap-4 overflow-x-auto pb-2">
                {items.map((property) => <SimilarCard key={property.id} property={property} />)}
            </div>
        </section>
    );
}

export default function MarketplaceShow({ property, onMarket = true, similar = [], recents = [], recommended = [], isFavourited = false, interestState = null, viewingSlots = [], applicationState = null, reportCategories = [], auth }) {
    const images = property.images?.length ? property.images : [];
    const [active, setActive] = useState(0);
    const [saved, setSaved] = useState(isFavourited);
    const [reportOpen, setReportOpen] = useState(false);
    const flash = usePage().props.flash || {};
    const enquiry = useForm({ message: '', phone: '' });
    const application = useForm({ message: '' });
    const interest = useForm({ note: '' });
    const report = useForm({ subject_type: 'property', subject_id: property.id, category: reportCategories[0] || '', description: '', priority: 'medium' });

    const isTenant = auth?.user?.roles?.some((role) => role.name === 'Tenant');
    // Favourites are tenant-only: guests are sent to sign in, other roles see no heart.
    const canFavourite = !auth?.user || isTenant;
    const canApply = !applicationState || applicationState.status === 'rejected';

    const toggleFavourite = () => {
        if (!auth?.user) {
            router.get(route('login'));
            return;
        }
        setSaved((current) => !current);
        router.post(route('tenant.favourites.toggle', property.id), {}, { preserveScroll: true });
    };

    const expressInterest = () => {
        if (!auth?.user) {
            router.get(route('login'));
            return;
        }
        interest.post(route('tenant.interests.store', property.id), {
            preserveScroll: true,
            onSuccess: () => interest.reset('note'),
        });
    };

    const withdrawInterest = () => {
        interest.post(route('tenant.interests.withdraw', interestState.id), { preserveScroll: true });
    };

    const sendEnquiry = (e) => {
        e.preventDefault();
        enquiry.post(route('tenant.enquiries.store', property.id), {
            preserveScroll: true,
            onSuccess: () => enquiry.reset('message', 'phone'),
        });
    };

    const sendApplication = (e) => {
        e.preventDefault();
        application.post(route('tenant.applications.store', property.id), {
            preserveScroll: true,
            onSuccess: () => application.reset('message'),
        });
    };

    const submitReport = (e) => {
        e.preventDefault();
        report.post(route('property.report', property.id), {
            preserveScroll: true,
            onSuccess: () => {
                report.reset('description');
                setReportOpen(false);
            },
        });
    };

    const formatSlot = (value) =>
        new Date(value).toLocaleString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });

    const location = [property.suburb, property.zone, property.city].filter(Boolean).join(', ');
    const area = property.building_size ? `${Number(property.building_size).toLocaleString()} m²` : null;
    const land = property.land_size ? `${Number(property.land_size).toLocaleString()} m² land` : null;
    // Decimal columns arrive as strings ("750.00"): add them as numbers, not text.
    const moveInCost = Number(property.deposit ?? 0) + Number(property.price ?? 0);
    const monthlyCosts = [
        ['Water', property.water_cost],
        ['Electricity', property.electricity_cost],
        ['Refuse', property.trash_cost],
    ].filter(([, amount]) => amount != null);
    const facts = [
        ['Security', optionLabel(SECURITY_TYPES, property.security_type)],
        ['Parking', optionLabel(PARKING_TYPES, property.parking_type)],
        ['Entrance', optionLabel(ENTRANCE_TYPES, property.entrance_type)],
        ['Bathroom', optionLabel(BATHROOM_TYPES, property.bathroom_type)],
        ['Preferred tenant', optionLabel(PREFERRED_TENANTS, property.preferred_tenant)],
        ['Minimum stay', property.minimum_stay != null ? `${property.minimum_stay} months` : null],
        ['Distance to CBD', property.distance_to_cbd != null ? `${Number(property.distance_to_cbd).toLocaleString()} km` : null],
        ['Year built', property.year_built ?? null],
        ['Children', yesNoOrDash(property.children_allowed)],
        ['Pets', yesNoOrDash(property.pets_allowed)],
        ['Smoking', yesNoOrDash(property.smoking_allowed)],
        ['Parties', yesNoOrDash(property.parties_allowed)],
    ].filter(([, value]) => value != null && value !== '');
    const memberSince = property.owner?.created_at ? new Date(property.owner.created_at).getFullYear() : null;
    const hasCoords = hasCoordinates(property);
    const exactLocation = Boolean(property.locationExact);
    const approxRadius = Number(property.approxRadiusM) || 500;
    const ownerVerified = Boolean(property.owner?.verified);
    const nowAvailable = !(property.available_from && new Date(property.available_from).getTime() > Date.now());

    return (
        <>
            <Head title={`${property.title} | ZimRent`} />

            <div className="min-h-screen app-bg pb-24 lg:pb-0">
                <header className="sticky top-0 z-[1050] border-b border-white/10 bg-brand">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <Link href={route('home')} aria-label="ZimRent home">
                            <Brand dark size={32} />
                        </Link>
                        <div className="flex items-center gap-5">
                            {auth?.user ? (
                                <Link href={route(dashboardRouteFor(auth.user.roles))} className="inline-flex h-9 items-center justify-center rounded-full bg-pitch px-4 text-sm font-semibold text-brand transition-colors hover:bg-white">
                                    My dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className="hidden text-sm font-medium text-white/75 hover:text-white sm:inline">
                                        Sign in
                                    </Link>
                                    <Link href={route('login')} className="inline-flex h-9 items-center justify-center rounded-full bg-pitch px-4 text-sm font-semibold text-brand transition-colors hover:bg-white">
                                        List your property
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <Link href={route('home')} className="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80">
                        <ArrowLeft className="h-4 w-4" /> Back to search results
                    </Link>

                    {!onMarket && (
                        <div role="status" className="mb-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5">
                            <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                            <div>
                                <p className="text-sm font-extrabold text-amber-900">This home is no longer on the market</p>
                                <p className="mt-0.5 text-sm text-amber-800/90">
                                    You can still see its details because it is linked to your account, but it is not accepting new enquiries, interest, viewings or applications.
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                        <div className="space-y-6">
                            {/* Gallery */}
                            <div className="surface overflow-hidden">
                                <div className="relative h-80 overflow-hidden sm:h-[28rem]">
                                    {images.length > 0 ? (
                                        <img src={images[active].path} alt={images[active].caption || property.title} className="h-full w-full object-cover" />
                                    ) : (
                                        <PropertyArt property={property} className="h-full" />
                                    )}
                                    {images.length > 1 && (
                                        <>
                                            <button
                                                type="button"
                                                aria-label="Previous photo"
                                                onClick={() => setActive((i) => (i - 1 + images.length) % images.length)}
                                                className="absolute left-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-800 shadow-lg transition hover:bg-white"
                                            >
                                                <ChevronLeft className="h-5 w-5" />
                                            </button>
                                            <button
                                                type="button"
                                                aria-label="Next photo"
                                                onClick={() => setActive((i) => (i + 1) % images.length)}
                                                className="absolute right-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-800 shadow-lg transition hover:bg-white"
                                            >
                                                <ChevronRight className="h-5 w-5" />
                                            </button>
                                        </>
                                    )}
                                    <div className="absolute left-4 top-4 flex flex-wrap gap-2">
                                        {property.featured && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-400 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider text-amber-950 shadow-lg">
                                                <Star className="h-3.5 w-3.5 fill-current" /> Featured
                                            </span>
                                        )}
                                        {property.verified && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/90 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider text-white shadow-lg backdrop-blur-sm">
                                                <BadgeCheck className="h-3.5 w-3.5" /> Verified property
                                            </span>
                                        )}
                                    </div>
                                    <span className="absolute bottom-3 right-3 rounded-full bg-black/50 px-2.5 py-1 text-[10px] font-black text-white backdrop-blur">
                                        {images.length > 0 ? `${active + 1} / ${images.length}` : '1 / 1'}
                                    </span>
                                </div>
                                {images.length > 1 && (
                                    <div className="flex gap-2 overflow-x-auto p-3">
                                        {images.map((image, i) => (
                                            <button
                                                key={image.id}
                                                type="button"
                                                onClick={() => setActive(i)}
                                                className={cn(
                                                    'h-20 w-28 shrink-0 overflow-hidden rounded-lg ring-2 transition-all',
                                                    i === active ? 'ring-primary' : 'ring-transparent opacity-70 hover:opacity-100'
                                                )}
                                            >
                                                <img src={image.path} alt={image.caption || property.title} className="h-full w-full object-cover" />
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Location & map */}
                            <section className="surface p-6 sm:p-8">
                                <h2 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Location</h2>
                                <p className="mt-3 flex items-center gap-2 text-[15px] font-semibold text-foreground">
                                    <MapPin className="h-5 w-5 shrink-0 text-emerald-500" />
                                    {[property.address, location].filter(Boolean).join(', ') || 'Location on request'}
                                </p>
                                {hasCoords ? (
                                    <>
                                        <div className="mt-4 overflow-hidden rounded-2xl border border-border">
                                            <MapEmbed property={property} exact={exactLocation} radius={approxRadius} />
                                        </div>
                                        {exactLocation ? (
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                <a
                                                    href={`https://www.google.com/maps/dir/?api=1&destination=${Number(property.latitude)},${Number(property.longitude)}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="btn-secondary h-9 px-4 text-sm"
                                                >
                                                    <Navigation className="h-4 w-4" /> Get directions
                                                </a>
                                                <a
                                                    href={`https://www.google.com/maps/search/?api=1&query=${Number(property.latitude)},${Number(property.longitude)}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="btn-secondary h-9 px-4 text-sm"
                                                >
                                                    <ExternalLink className="h-4 w-4" /> Open in Google Maps
                                                </a>
                                            </div>
                                        ) : (
                                            <p className="mt-3 flex items-start gap-2 rounded-2xl bg-muted/60 px-4 py-3 text-sm text-muted-foreground">
                                                <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                                                Approximate area — the exact location is shared once the owner accepts your viewing.
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <p className="mt-3 rounded-2xl bg-muted/60 px-4 py-3 text-sm text-muted-foreground">
                                        The exact location is shared with interested tenants once the owner accepts your viewing.
                                    </p>
                                )}
                            </section>

                            {/* Description */}
                            <section className="surface p-6 sm:p-8">
                                <h2 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground">About this home</h2>
                                <p className="mt-3 text-[15px] leading-relaxed text-foreground">{property.description || 'No description provided.'}</p>

                                <h2 className="mt-8 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Amenities</h2>
                                {property.amenities?.length ? (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {property.amenities.map((amenity) => (
                                            <span
                                                key={amenity}
                                                className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-3 py-1.5 text-xs font-semibold capitalize text-foreground"
                                            >
                                                <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />
                                                {amenity.replace(/_/g, ' ')}
                                            </span>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="mt-3 text-sm text-muted-foreground">No amenities listed.</p>
                                )}
                            </section>
                        </div>

                        {/* Sidebar */}
                        <aside className="space-y-4">
                            <div id="contact" className="surface scroll-mt-24 p-6">
                                <h1 className="text-balance text-2xl font-extrabold tracking-tight text-foreground">{property.title}</h1>
                                {location && (
                                    <p className="mt-1.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                                        <MapPin className="h-4 w-4 shrink-0 text-emerald-500" /> {location}
                                    </p>
                                )}

                                <div className="mt-5 flex flex-wrap items-baseline gap-2">
                                    <span className="text-3xl font-extrabold tracking-tight text-foreground">{formatPrice(property.price, property.currency)}</span>
                                    <span className="text-sm font-medium text-muted-foreground">{paymentPeriod(property.payment_terms)}</span>
                                    {property.deposit > 0 && <span className="ml-1 text-xs font-semibold text-muted-foreground">Deposit {formatPrice(property.deposit, property.currency)}</span>}
                                </div>

                                <div className="mt-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                    <span className="text-xs font-bold uppercase tracking-wider text-emerald-800">Availability</span>
                                    {onMarket ? (
                                        <span className={cn('inline-flex items-center gap-1.5 text-sm font-extrabold', nowAvailable ? 'text-emerald-700' : 'text-amber-700')}>
                                            <span className={cn('h-2 w-2 rounded-full', nowAvailable ? 'animate-pulse bg-emerald-500' : 'bg-amber-500')} />
                                            {availabilityOf(property)}
                                        </span>
                                    ) : (
                                        <StatusBadge status={property.status} />
                                    )}
                                </div>

                                <div className="mt-6 grid grid-cols-2 gap-3">
                                    <div className="rounded-xl bg-muted/60 p-3.5">
                                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><BedDouble className="h-4 w-4 text-primary" /> Bedrooms</div>
                                        <div className="mt-1 text-lg font-extrabold text-foreground">{property.bedrooms ?? '—'}</div>
                                    </div>
                                    <div className="rounded-xl bg-muted/60 p-3.5">
                                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><Bath className="h-4 w-4 text-primary" /> Bathrooms</div>
                                        <div className="mt-1 text-lg font-extrabold text-foreground">{property.bathrooms}</div>
                                    </div>
                                    {area && (
                                        <div className="rounded-xl bg-muted/60 p-3.5">
                                            <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><Ruler className="h-4 w-4 text-primary" /> Size</div>
                                            <div className="mt-1 text-lg font-extrabold text-foreground">{area}</div>
                                        </div>
                                    )}
                                    {land && (
                                        <div className="rounded-xl bg-muted/60 p-3.5">
                                            <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><Ruler className="h-4 w-4 text-primary" /> Land</div>
                                            <div className="mt-1 text-lg font-extrabold text-foreground">{land}</div>
                                        </div>
                                    )}
                                    <div className="rounded-xl bg-muted/60 p-3.5">
                                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><Armchair className="h-4 w-4 text-primary" /> Furnishing</div>
                                        <div className="mt-1 text-lg font-extrabold capitalize text-foreground">{property.furnished ? 'Furnished' : 'Unfurnished'}</div>
                                    </div>
                                    <div className="rounded-xl bg-muted/60 p-3.5">
                                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><CalendarClock className="h-4 w-4 text-primary" /> Type</div>
                                        <div className="mt-1 text-lg font-extrabold capitalize text-foreground">{typeLabels[property.property_type] || property.property_type}</div>
                                    </div>
                                </div>

                                {/* Structured listing facts */}
                                {facts.length > 0 && (
                                    <div className="mt-6 rounded-2xl border border-border bg-muted/40 p-4">
                                        <h3 className="text-xs font-extrabold uppercase tracking-wider text-muted-foreground">Good to know</h3>
                                        <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                                            {facts.map(([label, value]) => (
                                                <div key={label} className="flex flex-col">
                                                    <dt className="text-[10px] font-black uppercase tracking-[.12em] text-muted-foreground">{label}</dt>
                                                    <dd className="mt-0.5 font-bold capitalize text-foreground">{value}</dd>
                                                </div>
                                            ))}
                                        </dl>
                                    </div>
                                )}

                                {/* Financial breakdown */}
                                <div className="mt-6 rounded-2xl border border-border bg-muted/40 p-4">
                                    <h3 className="text-xs font-extrabold uppercase tracking-wider text-muted-foreground">Cost breakdown</h3>
                                    <dl className="mt-3 space-y-2 text-sm">
                                        <div className="flex items-center justify-between">
                                            <dt className="flex items-center gap-1.5 font-semibold text-muted-foreground"><DollarSign className="h-4 w-4 text-primary" /> Rent</dt>
                                            <dd className="font-extrabold text-foreground">{formatPrice(property.price, property.currency)}</dd>
                                        </div>
                                        {property.deposit > 0 && (
                                            <div className="flex items-center justify-between">
                                                <dt className="flex items-center gap-1.5 font-semibold text-muted-foreground"><KeyRound className="h-4 w-4 text-primary" /> Refundable deposit</dt>
                                                <dd className="font-extrabold text-foreground">{formatPrice(property.deposit, property.currency)}</dd>
                                            </div>
                                        )}
                                        {monthlyCosts.length > 0 && (
                                            <div className="border-t border-border pt-2">
                                                {monthlyCosts.map(([label, amount]) => (
                                                    <div key={label} className="flex items-center justify-between">
                                                        <dt className="font-semibold text-muted-foreground">{label} / month</dt>
                                                        <dd className="font-bold text-foreground">{formatPrice(amount, property.currency)}</dd>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between border-t border-border pt-2">
                                            <dt className="font-bold text-foreground">Move-in cost (first {property.payment_terms === 'yearly' ? 'year' : property.payment_terms === 'quarterly' ? 'quarter' : 'month'} + deposit)</dt>
                                            <dd className="text-base font-extrabold text-primary">{formatPrice(moveInCost, property.currency)}</dd>
                                        </div>
                                    </dl>
                                    {property.negotiable && (
                                        <p className="mt-3 inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Rent is negotiable</p>
                                    )}
                                </div>
                            </div>

                            {/* Owner info */}
                            <div className="surface p-6">
                                <h2 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Listed by Owner</h2>
                                <div className="mt-4 flex items-center gap-3">
                                    <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full brand-gradient text-base font-extrabold text-white">
                                        {(property.owner?.name || '?').charAt(0).toUpperCase()}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate font-bold text-foreground">{property.owner?.name || 'ZimRent owner'}</p>
                                        <p className="flex items-center gap-1 text-xs font-bold text-emerald-600">
                                            <UserCheck className="h-3.5 w-3.5" /> Direct owner — no agent
                                            {ownerVerified && <BadgeCheck className="h-3.5 w-3.5 text-emerald-600" />}
                                        </p>
                                        {memberSince && (
                                            <p className="mt-0.5 flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                                                <Clock className="h-3 w-3" /> Member since {memberSince}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <OwnerTrustBadge owner={property.owner} className="mt-3 px-3 py-1.5" />
                                {property.owner?.email && (
                                    <p className="mt-3 flex items-center gap-2 rounded-xl border border-border bg-muted/50 px-3.5 py-2.5 text-sm font-semibold text-muted-foreground">
                                        <Mail className="h-4 w-4 text-primary" /> {property.owner.email}
                                    </p>
                                )}

                                <div className="mt-5 flex flex-col gap-2">
                                    {flash.success && (
                                        <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm font-semibold text-emerald-800">
                                            {flash.success}
                                        </p>
                                    )}
                                    {flash.error && (
                                        <p role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-sm font-semibold text-rose-800">
                                            {flash.error}
                                        </p>
                                    )}

                                    {isTenant && onMarket &&
                                        (interestState ? (
                                            <div className="flex flex-col gap-2 rounded-2xl border border-emerald-500/30 bg-emerald-500/[0.06] p-3.5">
                                                <span className="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-emerald-600">
                                                    <Users className="h-4 w-4" /> Interest recorded
                                                </span>
                                                <p className="text-[11px] font-medium text-muted-foreground">
                                                    {interestState.status === 'contacted'
                                                        ? 'The owner has been in touch — you can keep the thread going from your enquiries.'
                                                        : interestState.status === 'archived'
                                                          ? 'You withdrew interest in this property.'
                                                          : 'The owner will notify you. Track it under My Interests.'}
                                                </p>
                                                <div className="flex items-center gap-2">
                                                    {interestState.status === 'archived' ? (
                                                        <button
                                                            type="button"
                                                            onClick={expressInterest}
                                                            disabled={interest.processing}
                                                            className={cn(buttonVariants({ size: 'sm' }), 'w-full bg-emerald-500 text-white hover:bg-emerald-400')}
                                                        >
                                                            <Users className="h-4 w-4" /> {interest.processing ? 'Recording…' : 'Re-express Interest'}
                                                        </button>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={withdrawInterest}
                                                            disabled={interest.processing}
                                                            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'w-full')}
                                                        >
                                                            <Undo2 className="h-4 w-4" /> Withdraw
                                                        </button>
                                                    )}
                                                    <Link
                                                        href={route('tenant.interests.index')}
                                                        className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'w-full')}
                                                    >
                                                        My Interests
                                                    </Link>
                                                </div>
                                            </div>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={expressInterest}
                                                disabled={interest.processing}
                                                className={cn(buttonVariants({ variant: 'outline' }), 'w-full border-emerald-500/40 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700')}
                                            >
                                                <Users className="h-4 w-4" /> {interest.processing ? 'Recording…' : 'Express Interest'}
                                            </button>
                                        ))}
                                    {isTenant && onMarket && interest.errors.note && (
                                        <p className="text-xs font-semibold text-rose-600">{interest.errors.note}</p>
                                    )}

                                    {!onMarket ? null : isTenant ? (
                                        <form onSubmit={sendEnquiry} className="flex flex-col gap-2">
                                            <label className="text-xs font-extrabold uppercase tracking-wider text-muted-foreground">Ask the owner</label>
                                            <textarea
                                                value={enquiry.data.message}
                                                onChange={(e) => enquiry.setData('message', e.target.value)}
                                                placeholder="Is this still available?..."
                                                rows={3}
                                                maxLength={1000}
                                                className="field w-full resize-none"
                                            />
                                            <input
                                                type="tel"
                                                value={enquiry.data.phone}
                                                onChange={(e) => enquiry.setData('phone', e.target.value)}
                                                placeholder="Your phone number (optional)"
                                                maxLength={20}
                                                className="field w-full"
                                            />
                                            {enquiry.errors.message && (
                                                <p className="text-xs font-semibold text-rose-600">{enquiry.errors.message}</p>
                                            )}
                                            {enquiry.errors.phone && (
                                                <p className="text-xs font-semibold text-rose-600">{enquiry.errors.phone}</p>
                                            )}
                                            <button
                                                type="submit"
                                                disabled={enquiry.processing}
                                                className={cn(buttonVariants(), 'w-full')}
                                            >
                                                <Send className="h-4 w-4" /> {enquiry.processing ? 'Sending…' : 'Send Enquiry'}
                                            </button>
                                            {property.owner_id && (
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(route('chat.start-direct', property.id))}
                                                    className={cn(buttonVariants({ variant: 'outline' }), 'w-full')}
                                                >
                                                    <MessageCircle className="h-4 w-4" /> Message owner (live chat)
                                                </button>
                                            )}
                                            <p className="pt-1 text-center text-[11px] font-medium text-muted-foreground">
                                                Free for tenants — the owner replies right here, no agent.
                                            </p>
                                        </form>
                                    ) : auth?.user ? (
                                        <p className="rounded-xl border border-border bg-muted/50 px-3.5 py-2.5 text-xs font-semibold text-muted-foreground">
                                            Enquiries, viewings, applications and favourites are for tenant accounts. Owners respond to enquiries from their inbox.
                                        </p>
                                    ) : (
                                        <Link href={route('login')} className={cn(buttonVariants(), 'w-full')}>
                                            <Send className="h-4 w-4" /> Enquire with Owner
                                        </Link>
                                    )}

                                    {isTenant && onMarket && (
                                        <ViewingBooker propertyId={property.id} slots={viewingSlots} />
                                    )}

                                    {isTenant && applicationState && (
                                        <div className="flex flex-col gap-2 rounded-2xl border border-primary/25 bg-primary/[0.03] p-3.5">
                                            <div className="flex items-center gap-2">
                                                <StatusBadge status={applicationState.status} />
                                                <p className="text-xs font-semibold text-muted-foreground">
                                                    {applicationState.status === 'approved'
                                                        ? 'Approved — the owner will reach out to start the lease.'
                                                        : applicationState.status === 'rejected'
                                                          ? 'Your last application was not approved for this property.'
                                                          : 'Application submitted — the owner will review it shortly.'}
                                                </p>
                                            </div>
                                            {applicationState.status === 'rejected' && applicationState.reject_reason && (
                                                <p className="rounded-xl border-l-4 border-l-rose-400 bg-rose-50/60 px-3 py-2 text-xs leading-relaxed text-foreground">
                                                    <span className="font-extrabold text-rose-700">Owner noted: </span>{applicationState.reject_reason}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {isTenant && onMarket && canApply && (
                                        <form onSubmit={sendApplication} className="flex flex-col gap-2 rounded-2xl border border-dashed border-primary/25 bg-primary/[0.03] p-3.5">
                                            <span className="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-primary">
                                                <ClipboardCheck className="h-4 w-4" /> {applicationState ? 'Apply Again' : 'Apply to Rent'}
                                            </span>
                                            <textarea
                                                value={application.data.message}
                                                onChange={(e) => application.setData('message', e.target.value)}
                                                placeholder="Tell the owner about yourself (optional)"
                                                rows={3}
                                                maxLength={1000}
                                                className="field w-full resize-none"
                                            />
                                            {application.errors.message && (
                                                <p className="text-xs font-semibold text-rose-600">{application.errors.message}</p>
                                            )}
                                            <button
                                                type="submit"
                                                disabled={application.processing}
                                                className={cn(buttonVariants(), 'w-full')}
                                            >
                                                <ClipboardCheck className="h-4 w-4" /> {application.processing ? 'Submitting…' : 'Apply now'}
                                            </button>
                                            <p className="pt-1 text-center text-[11px] font-medium text-muted-foreground">
                                                Free to apply — you can only hold one active application per property.
                                            </p>
                                        </form>
                                    )}

                                    {!auth?.user && onMarket && (
                                        <Link href={route('login')} className={cn(buttonVariants({ variant: 'outline' }), 'w-full border-primary/30 text-primary hover:bg-primary hover:text-white')}>
                                            <ClipboardCheck className="h-4 w-4" /> Apply to Rent
                                        </Link>
                                    )}
                                    {canFavourite && (
                                        <button
                                            type="button"
                                            onClick={toggleFavourite}
                                            aria-pressed={saved}
                                            className={cn(
                                                buttonVariants({ variant: 'outline' }),
                                                'w-full',
                                                saved
                                                    ? 'border-rose-200 bg-rose-50 text-rose-500 hover:bg-rose-100 hover:text-rose-600'
                                                    : 'border-border text-muted-foreground hover:border-rose-200 hover:bg-rose-50 hover:text-rose-500'
                                            )}
                                        >
                                            <Heart className={cn('h-4 w-4', saved && 'fill-rose-500')} />
                                            {saved ? 'Saved to favourites' : 'Save to favourites'}
                                        </button>
                                    )}
                                </div>
                            </div>

                            {/* Safety & report */}
                            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                                <p className="flex items-center gap-2 text-sm font-extrabold text-amber-900">
                                    <ShieldCheck className="h-5 w-5 shrink-0 text-amber-600" /> Stay safe on ZimRent
                                </p>
                                <p className="mt-2 text-sm leading-6 text-amber-800/90">
                                    Never send money before verifying the property and agreement, and only ever deal directly with the owner on this listing.
                                </p>
                                <button
                                    type="button"
                                    onClick={() => setReportOpen(true)}
                                    className="mt-4 inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-white px-4 py-2 text-xs font-black text-rose-600 transition hover:bg-rose-50"
                                >
                                    <Flag className="h-3.5 w-3.5" /> Report listing
                                </button>
                            </div>
                        </aside>
                    </div>

                    {/* Similar + personalisation rails */}
                    {similar.length > 0 && (
                        <section className="mt-12 border-t border-border/60 pt-10">
                            <ResultRail title="Similar homes nearby" icon={Building2} items={similar} />
                        </section>
                    )}
                    {recommended.length > 0 && (
                        <ResultRail title="You may also like" icon={Sparkles} items={recommended} />
                    )}
                    {recents.length > 0 && (
                        <ResultRail title="Recently viewed" icon={History} items={recents} />
                    )}
                </main>

                <footer className="dark-sidebar">
                    <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-4 py-10 sm:px-6 md:flex-row lg:px-8">
                        <Brand dark />
                        <p className="text-sm font-medium text-white/60">Rent directly. Live simply.</p>
                        <div className="flex gap-6 text-sm font-semibold text-white/70">
                            {auth?.user ? <Link href={route(dashboardRouteFor(auth.user.roles))} className="transition-colors hover:text-emerald-300">My Dashboard</Link> : <Link href={route('login')} className="transition-colors hover:text-emerald-300">Sign In</Link>}
                            <Link href={route('login')} className="transition-colors hover:text-emerald-300">For Owners</Link>
                            <Link href={route('login')} className="transition-colors hover:text-emerald-300">For Tenants</Link>
                        </div>
                    </div>
                </footer>
            </div>

            {/* Mobile sticky CTAs */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
                <div className="mx-auto flex max-w-md items-center gap-2">
                    {canFavourite && (
                        <button
                            type="button"
                            onClick={toggleFavourite}
                            aria-pressed={saved}
                            aria-label={saved ? 'Remove from favourites' : 'Save to favourites'}
                            className={cn(
                                'grid h-12 w-12 shrink-0 place-items-center rounded-2xl border transition',
                                saved
                                    ? 'border-rose-200 bg-rose-50 text-rose-500'
                                    : 'border-slate-200 bg-white text-slate-500'
                            )}
                        >
                            <Heart className={cn('h-5 w-5', saved && 'fill-rose-500')} />
                        </button>
                    )}
                    <a href="#contact" className="flex h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-slate-900 text-sm font-black text-white transition hover:bg-emerald-600">
                        <Send className="h-4 w-4" /> {!onMarket ? 'Listing details' : isTenant ? 'Enquire · Apply' : 'Contact owner'}
                    </a>
                </div>
            </div>

            {/* Report modal */}
            {reportOpen && (
                <div className="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label="Report this listing">
                    <button type="button" aria-label="Close report" className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" onClick={() => setReportOpen(false)} />
                    <div className="absolute inset-x-0 bottom-0 mx-auto max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:my-auto sm:rounded-3xl">
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-rose-100 text-rose-600">
                                    <ShieldAlert className="h-5 w-5" />
                                </span>
                                <div>
                                    <h3 className="text-lg font-black tracking-tight text-slate-950">Report this listing</h3>
                                    <p className="text-xs font-semibold text-slate-500">Our moderation team reviews every report.</p>
                                </div>
                            </div>
                            <button type="button" onClick={() => setReportOpen(false)} aria-label="Close report" className="grid h-10 w-10 shrink-0 place-items-center rounded-full hover:bg-slate-100">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitReport} className="mt-5 flex flex-col gap-4">
                            <label className="block">
                                <span className="mb-1.5 block text-xs font-black uppercase tracking-[.14em] text-slate-500">Reason</span>
                                <select
                                    value={report.data.category}
                                    onChange={(e) => report.setData('category', e.target.value)}
                                    className="field w-full"
                                >
                                    {reportCategories.map((category) => (
                                        <option key={category} value={category}>
                                            {reportCategoryLabels[category] || category.replace(/_/g, ' ')}
                                        </option>
                                    ))}
                                </select>
                                {report.errors.category && <span className="mt-1 block text-xs font-semibold text-rose-600">{report.errors.category}</span>}
                            </label>

                            <label className="block">
                                <span className="mb-1.5 block text-xs font-black uppercase tracking-[.14em] text-slate-500">Explain what happened</span>
                                <textarea
                                    value={report.data.description}
                                    onChange={(e) => report.setData('description', e.target.value)}
                                    placeholder="Give us the details so we can investigate…"
                                    rows={4}
                                    maxLength={2000}
                                    className="field w-full resize-none"
                                />
                                {report.errors.description && <span className="mt-1 block text-xs font-semibold text-rose-600">{report.errors.description}</span>}
                            </label>

                            <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setReportOpen(false)}
                                    className="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={report.processing}
                                    className="inline-flex h-11 items-center gap-2 rounded-2xl bg-rose-600 px-5 text-sm font-black text-white transition hover:bg-rose-500 disabled:opacity-60"
                                >
                                    <Flag className="h-4 w-4" /> {report.processing ? 'Submitting…' : 'Submit report'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            <ChatWidget />
        </>
    );
}