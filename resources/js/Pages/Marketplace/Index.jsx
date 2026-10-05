import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {
    Search, MapPin, BadgeCheck, Star, ShieldCheck, Landmark, BadgeDollarSign, ArrowRight,
    DoorOpen, Store, Home, Building2, ChevronDown, SlidersHorizontal, Heart, Sparkles,
    CheckCircle2, Menu, X, LayoutGrid, List, UserCheck, RotateCcw, Map as MapIcon, Save,
    ChevronLeft, ChevronRight, ArrowLeftRight, History, Zap, KeyRound, Maximize2,
} from 'lucide-react';
import Brand from '@/Components/Shared/Brand';
import PropertyArt from '@/Components/Shared/PropertyArt';
import EmptyState from '@/Components/Shared/EmptyState';
import OwnerTrustBadge from '@/Components/Shared/OwnerTrustBadge';
import MapStyleToggle from '@/Components/Shared/MapStyleToggle';
import ChatWidget from '@/Components/Shared/ChatWidget';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import { dashboardRouteFor } from '@/lib/roles';
import {
    createBaseLayers, createMap, hasCoordinates, pricePinIcon, propertyPopupHtml, DEFAULT_CENTER,
} from '@/lib/map';
import {
    TYPE_LABELS as typeLabels, formatPrice, priceSuffix,
    PAYMENT_TERM_LABELS, SECURITY_TYPES, PARKING_TYPES, PREFERRED_TENANTS,
} from '@/lib/listing';

const typeIcons = {
    house: Home, flat: Building2, apartment: Building2, townhouse: Building2, cottage: Home,
    room: DoorOpen, commercial: Store, land: Landmark,
};

const priceTiers = [['500', 'Under $500'], ['1000', 'Under $1,000'], ['3000', 'Under $3,000']];
const bedroomOptions = ['0', '1', '2', '3', '4'];
const bathroomOptions = ['1', '2', '3', '4'];

const PARTIAL_PROPS = [
    'properties', 'total', 'pagination', 'cities', 'zones', 'featured', 'justListed', 'filters',
    'marketplace', 'explore', 'recommended', 'recentlyViewed', 'favouriteIds',
];

const NO_IDS = [];

const ctaPitch ='inline-flex items-center justify-center gap-2 rounded-full bg-pitch font-semibold text-brand transition-colors hover:bg-white';

const tag = 'inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-900 shadow-sm ring-1 ring-black/5';

const timeAgo = (dateValue) => {
    if (!dateValue) return null;
    const days = Math.floor((Date.now() - new Date(dateValue).getTime()) / 86400000);
    if (days < 1) return 'Listed today';
    if (days === 1) return 'Listed yesterday';
    if (days < 30) return `Listed ${days} days ago`;
    const months = Math.floor(days / 30);
    return `Listed ${months} month${months === 1 ? '' : 's'} ago`;
};

const isNewListing = (property) =>
    Boolean(property.created_at) && Date.now() - new Date(property.created_at).getTime() < 7 * 86400000;

const availabilityOf = (property) => {
    if (property.available_from && new Date(property.available_from).getTime() > Date.now()) {
        return `Available from ${new Date(property.available_from).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`;
    }
    return 'Available now';
};

const isAvailableNow = (property) =>
    !(property.available_from && new Date(property.available_from).getTime() > Date.now());

const areaOf = (property) =>
    property.building_size > 0 ? `${Number(property.building_size).toLocaleString()} m²` : null;

const locationOf = (property) =>
    [property.suburb, property.zone, property.city].filter(Boolean).join(', ') || 'Location on request';

const specsOf = (property) => [
    typeLabels[property.property_type] || property.property_type,
    property.bedrooms > 0 ? `${property.bedrooms} bed${property.bedrooms === 1 ? '' : 's'}` : null,
    `${property.bathrooms} bath${Number(property.bathrooms) === 1 ? '' : 's'}`,
    areaOf(property),
    property.furnished ? 'Furnished' : 'Unfurnished',
].filter(Boolean).join(' · ');

function PropertyMedia({ property, className, badge }) {
    const images = property.images?.length ? property.images : null;
    const [index, setIndex] = useState(0);

    if (!images) {
        return <PropertyArt property={property} className={className} icon={badge} />;
    }

    const current = images[index] || images[0];

    return (
        <div className={cn('group/media relative overflow-hidden', className)}>
            <img
                src={current.path}
                alt={current.caption || property.title}
                className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
            />
            {images.length > 1 && (
                <>
                    <button
                        type="button"
                        aria-label="Previous photo"
                        onClick={(e) => { e.preventDefault(); e.stopPropagation(); setIndex((i) => (i - 1 + images.length) % images.length); }}
                        className="absolute left-2.5 top-1/2 z-10 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-slate-900 opacity-0 shadow-md transition group-hover/media:opacity-100 hover:scale-105"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        aria-label="Next photo"
                        onClick={(e) => { e.preventDefault(); e.stopPropagation(); setIndex((i) => (i + 1) % images.length); }}
                        className="absolute right-2.5 top-1/2 z-10 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-slate-900 opacity-0 shadow-md transition group-hover/media:opacity-100 hover:scale-105"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                    <div className="absolute bottom-2.5 left-1/2 z-10 flex -translate-x-1/2 gap-1" aria-label={`Photo ${index + 1} of ${images.length}`}>
                        {images.slice(0, 6).map((image, i) => (
                            <span key={image.path} className={cn('h-1.5 w-1.5 rounded-full transition', i === index ? 'bg-white' : 'bg-white/55')} />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

const isPopular = (property, threshold) => {
    if (!threshold) return false;
    return (property.views_count ?? 0) >= threshold.views && (property.favourites_count ?? 0) >= threshold.saves;
};

function PropertyBadges({ property, threshold, isFavourited, onToggleFavourite }) {
    return (
        <div className="absolute inset-x-0 top-0 z-10 flex items-start justify-between gap-2 p-3">
            <div className="flex flex-wrap gap-1.5">
                {[
                    property.verified && <span key="verified" className={tag}><BadgeCheck className="h-3.5 w-3.5 text-brand" /> Verified</span>,
                    property.featured && <span key="featured" className={tag}><Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" /> Featured</span>,
                    isPopular(property, threshold) && <span key="popular" className={tag}><Zap className="h-3.5 w-3.5 fill-orange-500 text-orange-500" /> Popular</span>,
                    isNewListing(property) && <span key="new" className={tag}><Sparkles className="h-3.5 w-3.5 text-brand" /> New</span>,
                ].filter(Boolean).slice(0, 2)}
            </div>
            {onToggleFavourite && (
                <button
                    type="button"
                    onClick={() => onToggleFavourite(property.id)}
                    aria-pressed={isFavourited}
                    aria-label={isFavourited ? 'Remove from favourites' : 'Save to favourites'}
                    className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white text-slate-900 shadow-sm ring-1 ring-black/5 transition hover:scale-105"
                >
                    <Heart className={cn('h-4 w-4', isFavourited && 'fill-rose-500 text-rose-500')} />
                </button>
            )}
        </div>
    );
}

function CompareChip({ enabled, selected, onToggle }) {
    if (!enabled) return null;
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-pressed={selected}
            className={cn('inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-xs font-semibold shadow-sm ring-1 transition',
                selected ? 'bg-brand text-white ring-brand' : 'bg-white text-slate-900 ring-black/5 hover:bg-slate-50')}
        >
            <ArrowLeftRight className="h-3.5 w-3.5" /> {selected ? 'Comparing' : 'Compare'}
        </button>
    );
}

function AvailabilityLine({ property }) {
    const now = isAvailableNow(property);
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-xs font-medium', now ? 'text-green-700' : 'text-amber-700')}>
            <span className={cn('h-1.5 w-1.5 rounded-full', now ? 'bg-green-500' : 'bg-amber-500')} />
            {availabilityOf(property)}
        </span>
    );
}

function Price({ property, className }) {
    return (
        <p className={cn('text-slate-900', className)}>
            <span className="font-semibold">{formatPrice(property.price, property.currency)}</span>
            <span className="text-slate-500"> {priceSuffix(property.payment_terms)}</span>
        </p>
    );
}

function CardText({ property, onQuickView, large = false }) {
    return (
        <div className="flex flex-1 flex-col">
            <div className="flex items-start justify-between gap-3">
                <h3 className={cn('truncate font-semibold text-slate-900', large ? 'text-lg' : 'text-[15px]')}>
                    <Link href={route('property.show', property.id)} className="hover:underline">{property.title}</Link>
                </h3>
                {property.owner?.verified && <UserCheck className="mt-0.5 h-4 w-4 shrink-0 text-brand" aria-label="Verified owner" />}
            </div>
            <p className="mt-0.5 truncate text-sm text-slate-500">{locationOf(property)}</p>
            <p className="mt-0.5 truncate text-sm text-slate-500">{specsOf(property)}</p>
            <Price property={property} className={cn('mt-2', large ? 'text-lg' : 'text-[15px]')} />
            {property.deposit > 0 && <p className="text-xs text-slate-500">+ {formatPrice(property.deposit, property.currency)} deposit</p>}
            <div className="mt-auto flex items-center justify-between gap-3 pt-3">
                <AvailabilityLine property={property} />
                <div className="flex items-center gap-3 text-sm font-medium">
                    {onQuickView && (
                        <button type="button" onClick={() => onQuickView(property)} className="text-slate-600 hover:text-slate-900">Quick view</button>
                    )}
                    <Link href={route('property.show', property.id)} className="inline-flex items-center gap-0.5 text-brand hover:underline">
                        Details <ChevronRight className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </div>
    );
}

function PropertyCard({ property, threshold, compareEnabled, compareSelected, onToggleCompare, isFavourited, onToggleFavourite, onQuickView }) {
    return (
        <article className="group flex flex-col">
            <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-slate-100">
                <Link href={route('property.show', property.id)} className="block h-full" tabIndex={-1}>
                    <PropertyMedia property={property} className="h-full w-full" />
                </Link>
                <PropertyBadges property={property} threshold={threshold} isFavourited={isFavourited} onToggleFavourite={onToggleFavourite} />
                <div className="absolute bottom-3 right-3 z-10">
                    <CompareChip enabled={compareEnabled} selected={compareSelected} onToggle={() => onToggleCompare(property)} />
                </div>
            </div>
            <div className="flex flex-1 flex-col px-0.5 pt-3.5">
                <CardText property={property} onQuickView={onQuickView} />
            </div>
        </article>
    );
}

function PropertyListRow({ property, threshold, compareEnabled, compareSelected, onToggleCompare, isFavourited, onToggleFavourite, onQuickView }) {
    return (
        <article className="group flex flex-col gap-5 border-b border-slate-200 pb-6 sm:flex-row">
            <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-slate-100 sm:w-80 sm:shrink-0">
                <Link href={route('property.show', property.id)} className="block h-full" tabIndex={-1}>
                    <PropertyMedia property={property} className="h-full w-full" />
                </Link>
                <PropertyBadges property={property} threshold={threshold} isFavourited={isFavourited} onToggleFavourite={onToggleFavourite} />
                <div className="absolute bottom-3 right-3 z-10">
                    <CompareChip enabled={compareEnabled} selected={compareSelected} onToggle={() => onToggleCompare(property)} />
                </div>
            </div>
            <div className="flex flex-1 flex-col py-1">
                <CardText property={property} onQuickView={onQuickView} large />
                {timeAgo(property.created_at) && <p className="mt-2 text-xs text-slate-400">{timeAgo(property.created_at)}</p>}
            </div>
        </article>
    );
}

function MapListItem({ property, active, scrollIntoView, onHover, isFavourited, onToggleFavourite }) {
    const ref = useRef(null);

    useEffect(() => {
        if (active && scrollIntoView) ref.current?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }, [active, scrollIntoView]);

    return (
        <article
            ref={ref}
            onMouseEnter={() => onHover(property.id)}
            onMouseLeave={() => onHover(null)}
            className={cn('flex gap-4 rounded-2xl p-2.5 transition-colors', active ? 'bg-slate-100' : 'hover:bg-slate-50')}
        >
            <Link href={route('property.show', property.id)} className="relative block h-28 w-36 shrink-0 overflow-hidden rounded-xl bg-slate-100" tabIndex={-1}>
                <PropertyMedia property={property} className="h-full w-full" />
            </Link>
            <div className="flex min-w-0 flex-1 flex-col py-0.5">
                <div className="flex items-start justify-between gap-2">
                    <h3 className="truncate text-[15px] font-semibold text-slate-900">
                        <Link href={route('property.show', property.id)} className="hover:underline">{property.title}</Link>
                    </h3>
                    {onToggleFavourite && (
                        <button type="button" onClick={() => onToggleFavourite(property.id)} aria-label={isFavourited ? 'Remove from favourites' : 'Save to favourites'} className="shrink-0 text-slate-400 hover:text-rose-500">
                            <Heart className={cn('h-4 w-4', isFavourited && 'fill-rose-500 text-rose-500')} />
                        </button>
                    )}
                </div>
                <p className="truncate text-sm text-slate-500">{locationOf(property)}</p>
                <p className="truncate text-sm text-slate-500">{specsOf(property)}</p>
                <div className="mt-auto flex items-center justify-between gap-2">
                    <Price property={property} className="text-[15px]" />
                    {!hasCoordinates(property) && <span className="text-[11px] text-slate-400">Not on map</span>}
                </div>
            </div>
        </article>
    );
}

function JustListedCard({ property }) {
    return (
        <Link href={route('property.show', property.id)} className="group w-64 shrink-0 snap-start">
            <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-slate-200">
                <PropertyMedia property={property} className="h-full w-full" />
                {isNewListing(property) && <span className={cn(tag, 'absolute left-3 top-3')}>New</span>}
            </div>
            <h4 className="mt-3 truncate text-[15px] font-semibold text-slate-900 group-hover:underline">{property.title}</h4>
            <p className="truncate text-sm text-slate-500">{[property.suburb, property.city].filter(Boolean).join(', ') || typeLabels[property.property_type]}</p>
            <Price property={property} className="mt-1 text-[15px]" />
        </Link>
    );
}

function QuickViewPanel({ property, onClose, isFavourited, onToggleFavourite }) {
    const facts = [
        ['Type', typeLabels[property.property_type] || property.property_type],
        ['Bedrooms', property.bedrooms != null ? property.bedrooms : '—'],
        ['Bathrooms', property.bathrooms],
        ['Size', areaOf(property) || '—'],
        ['Furnishing', property.furnished ? 'Furnished' : 'Unfurnished'],
        ['Listed', timeAgo(property.created_at) || '—'],
    ];
    return (
        <div className="fixed inset-0 z-[1100]" role="dialog" aria-modal="true" aria-label={`${property.title} quick view`}>
            <button type="button" aria-label="Close quick view" className="absolute inset-0 bg-black/40 animate-fade-in" onClick={onClose} />
            <div className="absolute inset-y-0 right-0 flex w-full max-w-md flex-col overflow-hidden bg-white shadow-2xl animate-toast-in">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                    <p className="text-sm font-semibold text-slate-900">Quick view</p>
                    <button type="button" onClick={onClose} aria-label="Close quick view" className="grid h-9 w-9 place-items-center rounded-full text-slate-600 hover:bg-slate-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto">
                    <div className="relative aspect-[4/3] bg-slate-100">
                        <PropertyMedia property={property} className="h-full w-full" />
                        <div className="absolute left-3 top-3 flex flex-wrap gap-1.5">
                            {property.featured && <span className={tag}><Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" /> Featured</span>}
                            <OwnerTrustBadge owner={property.owner} />
                            {property.verified && <span className={tag}><BadgeCheck className="h-3.5 w-3.5 text-brand" /> Verified</span>}
                        </div>
                    </div>
                    <div className="p-6">
                        <h3 className="text-2xl font-semibold tracking-tight text-slate-900">{property.title}</h3>
                        <p className="mt-1 text-sm text-slate-500">{locationOf(property)}</p>
                        <div className="mt-4 flex items-end justify-between gap-3">
                            <Price property={property} className="text-2xl" />
                            <AvailabilityLine property={property} />
                        </div>
                        {property.deposit > 0 && <p className="mt-1 text-sm text-slate-500">Deposit {formatPrice(property.deposit, property.currency)}</p>}

                        <dl className="mt-6 grid grid-cols-2 border-t border-slate-200 sm:grid-cols-3">
                            {facts.map(([label, value]) => (
                                <div key={label} className="border-b border-slate-200 py-3">
                                    <dt className="text-xs text-slate-500">{label}</dt>
                                    <dd className="mt-0.5 text-sm font-semibold text-slate-900">{value}</dd>
                                </div>
                            ))}
                        </dl>

                        <div className="mt-6 flex gap-3 rounded-2xl bg-slate-100 p-4">
                            <ShieldCheck className="h-5 w-5 shrink-0 text-brand" />
                            <p className="text-sm leading-6 text-slate-600">
                                <span className="font-semibold text-slate-900">Listed directly by the owner. </span>
                                {property.verified
                                    ? 'This listing has been verified by ZimRent. Never send money before viewing the property first.'
                                    : 'Never send money or documents before viewing the property first.'}
                            </p>
                        </div>

                        {property.amenities?.length > 0 && (
                            <div className="mt-6">
                                <p className="text-sm font-semibold text-slate-900">Amenities</p>
                                <ul className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm text-slate-600">
                                    {property.amenities.slice(0, 8).map((amenity) => (
                                        <li key={amenity} className="flex items-center gap-2 capitalize"><CheckCircle2 className="h-4 w-4 text-brand" /> {amenity.replace(/_/g, ' ')}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                </div>
                <div className="flex shrink-0 items-center gap-2 border-t border-slate-200 p-4">
                    {onToggleFavourite && (
                        <button type="button" onClick={() => onToggleFavourite(property.id)} className="btn-secondary h-11 flex-1 text-sm">
                            <Heart className={cn('h-4 w-4', isFavourited && 'fill-rose-500 text-rose-500')} /> {isFavourited ? 'Saved' : 'Save'}
                        </button>
                    )}
                    <Link href={route('property.show', property.id)} className="btn-primary h-11 flex-[2] text-sm">
                        View full listing
                    </Link>
                </div>
            </div>
        </div>
    );
}

function SkeletonGrid() {
    return (
        <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i}>
                    <div className="aspect-[4/3] animate-pulse rounded-2xl bg-slate-200" />
                    <div className="mt-4 space-y-2">
                        <div className="h-4 w-2/3 animate-pulse rounded bg-slate-200" />
                        <div className="h-3 w-1/2 animate-pulse rounded bg-slate-100" />
                        <div className="h-3 w-1/3 animate-pulse rounded bg-slate-100" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function ComparePanel({ items, onClose, onRemove }) {
    const rows = [
        ['Rent', (p) => `${formatPrice(p.price, p.currency)}${priceSuffix(p.payment_terms)}`],
        ['Deposit', (p) => p.deposit > 0 ? formatPrice(p.deposit, p.currency) : '—'],
        ['Type', (p) => typeLabels[p.property_type] || p.property_type],
        ['Bedrooms', (p) => p.bedrooms],
        ['Bathrooms', (p) => p.bathrooms],
        ['Size', (p) => areaOf(p)],
        ['Furnished', (p) => p.furnished ? 'Yes' : 'No'],
        ['Verified', (p) => p.verified ? 'Yes' : 'No'],
        ['Available', (p) => p.available_from && new Date(p.available_from).getTime() > Date.now() ? availabilityOf(p) : 'Now'],
    ];

    return (
        <div className="fixed inset-0 z-[1100]" role="dialog" aria-modal="true" aria-label="Compare properties">
            <button type="button" aria-label="Close compare" className="absolute inset-0 bg-black/40 animate-fade-in" onClick={onClose} />
            <div className="absolute inset-x-0 bottom-0 flex max-h-[88vh] flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:inset-x-auto sm:inset-y-0 sm:right-0 sm:w-[880px] sm:max-w-[94vw] sm:rounded-none">
                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 className="text-lg font-semibold tracking-tight text-slate-900">Compare {items.length} propert{items.length === 1 ? 'y' : 'ies'}</h3>
                        <p className="text-sm text-slate-500">Side by side, no spreadsheet needed.</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Close compare" className="grid h-9 w-9 place-items-center rounded-full hover:bg-slate-100"><X className="h-5 w-5" /></button>
                </div>
                <div className="flex-1 overflow-auto px-6 py-5">
                    <table className="w-full table-fixed text-sm">
                        <thead>
                            <tr>
                                <th className="w-32" />
                                {items.map((property) => (
                                    <th key={property.id} className="px-2 pb-4 text-left align-top font-normal">
                                        <PropertyMedia property={property} className="aspect-[4/3] rounded-xl" />
                                        <p className="mt-2 truncate font-semibold text-slate-900">{property.title}</p>
                                        <button type="button" onClick={() => onRemove(property.id)} className="mt-0.5 text-xs font-medium text-slate-500 hover:text-rose-600">Remove</button>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map(([label, get]) => (
                                <tr key={label} className="border-t border-slate-200">
                                    <th scope="row" className="py-3 text-left font-normal text-slate-500">{label}</th>
                                    {items.map((property) => <td key={property.id} className="px-2 py-3 font-medium text-slate-900">{get(property) ?? '—'}</td>)}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

function MapResults({ properties, total, activeId, onActivate, center }) {
    const mountRef = useRef(null);
    const mapRef = useRef(null);
    const layersRef = useRef(null);
    const markersRef = useRef(new Map());
    const [style, setStyle] = useState('map');

    const located = properties.filter(hasCoordinates);

    useEffect(() => {
        if (!mountRef.current) return undefined;
        const map = createMap(mountRef.current, { scrollWheelZoom: true });
        layersRef.current = createBaseLayers();
        layersRef.current.map.addTo(map);
        mapRef.current = map;
        const resize = new ResizeObserver(() => map.invalidateSize());
        resize.observe(mountRef.current);

        return () => {
            resize.disconnect();
            map.remove();
            mapRef.current = null;
        };
    }, []);

    useEffect(() => {
        const map = mapRef.current;
        const layers = layersRef.current;
        if (!map || !layers) return;
        Object.entries(layers).forEach(([key, layer]) => {
            if (key === style) layer.addTo(map);
            else map.removeLayer(layer);
        });
    }, [style]);

    useEffect(() => {
        const map = mapRef.current;
        if (!map) return undefined;

        const markers = new Map();
        located.forEach((property) => {
            const marker = L.marker([Number(property.latitude), Number(property.longitude)], {
                icon: pricePinIcon(property),
                riseOnHover: true,
                keyboard: true,
                title: property.title,
            })
                .addTo(map)
                .bindPopup(propertyPopupHtml(property, {
                    href: route('property.show', property.id),
                    suffix: priceSuffix(property.payment_terms),
                    meta: property.bedrooms > 0 ? `${property.bedrooms} bed` : typeLabels[property.property_type],
                }), { closeButton: false, className: 'dz-map-pop', offset: [0, -34], autoPanPadding: [40, 40] });
            marker.on('click', () => onActivate(property.id, 'map'));
            markers.set(property.id, marker);
        });
        markersRef.current = markers;

        if (located.length) {
            map.fitBounds(L.latLngBounds(located.map((p) => [Number(p.latitude), Number(p.longitude)])), { padding: [60, 60], maxZoom: 15 });
        } else {
            map.setView([center?.lat ?? DEFAULT_CENTER.lat, center?.lng ?? DEFAULT_CENTER.lng], center?.zoom ?? DEFAULT_CENTER.zoom);
        }

        return () => markers.forEach((marker) => marker.remove());
    }, [properties]);

    useEffect(() => {
        markersRef.current.forEach((marker, id) => {
            const property = located.find((p) => p.id === id);
            if (!property) return;
            marker.setIcon(pricePinIcon(property, id === activeId));
            marker.setZIndexOffset(id === activeId ? 1000 : 0);
        });
    }, [activeId, properties]);

    const fitAll = () => {
        if (!mapRef.current || !located.length) return;
        mapRef.current.fitBounds(L.latLngBounds(located.map((p) => [Number(p.latitude), Number(p.longitude)])), { padding: [60, 60], maxZoom: 15 });
    };

    return (
        <div className="dz-map relative isolate h-full w-full">
            <div ref={mountRef} className="h-full w-full" aria-label="Property map" />
            <MapStyleToggle value={style} onChange={setStyle} />
            <button type="button" onClick={fitAll} className="absolute right-3 top-3 z-[1000] inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-900 shadow-[0_2px_10px_rgba(0,0,0,0.14)] hover:bg-slate-50">
                <Maximize2 className="h-3.5 w-3.5" /> Show all
            </button>
            <div className="pointer-events-none absolute bottom-3 left-3 z-[1000] rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-[0_2px_10px_rgba(0,0,0,0.14)]">
                {located.length} of {total.toLocaleString()} home{total === 1 ? '' : 's'} · approximate areas
            </div>
        </div>
    );
}

function HeroSearch({ onSubmit, suggestionUrl, initial }) {
    const [text, setText] = useState(initial || '');
    const [suggestions, setSuggestions] = useState([]);
    const [focused, setFocused] = useState(false);
    const timer = useRef();
    const controller = useRef();

    useEffect(() => () => controller.current?.abort(), []);

    const fetchSuggestions = (value) => {
        const term = value.trim();
        if (!term) { setSuggestions([]); return; }

        clearTimeout(timer.current);
        timer.current = setTimeout(async () => {
            controller.current?.abort();
            controller.current = new AbortController();
            try {
                const response = await fetch(`${suggestionUrl}?q=${encodeURIComponent(term)}`, { signal: controller.current.signal });
                if (!response.ok) return;
                const data = await response.json();
                setSuggestions(data || []);
            } catch (error) {
                if (error.name !== 'AbortError') setSuggestions([]);
            }
        }, 220);
    };

    const submit = (value, navigate) => {
        const term = typeof value === 'string' ? value : text;
        if (!term.trim()) return;
        setSuggestions([]);
        navigate
            ? router.get(route('property.show', navigate))
            : onSubmit(term);
    };

    return (
        <div className="relative z-30 mt-9 w-full max-w-xl text-left">
            <div className="flex items-center gap-2 rounded-full bg-white p-1.5 pl-5 shadow-[0_12px_30px_rgba(0,0,0,0.25)] transition focus-within:ring-4 focus-within:ring-pitch/40">
                <Search className="pointer-events-none h-5 w-5 shrink-0 text-slate-500" />
                <input
                    value={text}
                    onChange={(e) => { setText(e.target.value); fetchSuggestions(e.target.value); }}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setTimeout(() => setFocused(false), 160)}
                    onKeyDown={(e) => e.key === 'Enter' && submit(text, null)}
                    placeholder="Suburb, city or property — e.g. “3 bed flat in Borrowdale”"
                    className="h-11 w-full min-w-0 bg-transparent text-[15px] text-slate-900 outline-none placeholder:text-slate-400"
                    aria-label="Search properties"
                />
                <button type="button" onClick={() => submit(text, null)} className={cn(ctaPitch, 'h-11 shrink-0 px-6 text-sm')}>
                    Search
                </button>
            </div>

            {focused && suggestions.length > 0 && (
                <div className="absolute inset-x-0 top-full mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1.5 shadow-xl">
                    {suggestions.map((suggestion) => (
                        <button
                            key={`${suggestion.type}:${suggestion.label}`}
                            type="button"
                            onMouseDown={(e) => {
                                e.preventDefault();
                                if (suggestion.type === 'title' && suggestion.id) {
                                    submit(suggestion.label, suggestion.id);
                                } else {
                                    setText(suggestion.type === 'suburb' && suggestion.city ? `${suggestion.label}, ${suggestion.city}` : suggestion.label);
                                    submit(suggestion.type === 'suburb' && suggestion.city ? `${suggestion.label}, ${suggestion.city}` : suggestion.label, null);
                                }
                            }}
                            className="flex w-full items-center gap-3 px-5 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-100"
                        >
                            <MapPin className="h-4 w-4 shrink-0 text-slate-400" />
                            <span className="truncate">{suggestion.label}</span>
                            {suggestion.type === 'title' && suggestion.city && <span className="ml-auto shrink-0 text-xs text-slate-400">{suggestion.city}</span>}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function SectionHeading({ eyebrow, title, aside }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
                {eyebrow && <p className="text-sm font-semibold text-brand">{eyebrow}</p>}
                <h2 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">{title}</h2>
            </div>
            {aside}
        </div>
    );
}

function ExploreNeighbourhoods({ places, onPick }) {
    if (!places?.length) return null;
    return (
        <section id="neighbourhoods" className="scroll-mt-20 bg-white">
            <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <SectionHeading eyebrow="Neighbourhoods" title="Explore by area." />
                <div className="mt-10 grid grid-cols-1 border-t border-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                    {places.map((place) => (
                        <button
                            key={`${place.suburb}:${place.city}`}
                            type="button"
                            onClick={() => onPick(place)}
                            className="group flex items-center justify-between gap-3 border-b border-slate-200 py-5 pr-4 text-left sm:[&:nth-child(odd)]:pr-8"
                        >
                            <span>
                                <span className="block text-[17px] font-semibold text-slate-900">{place.suburb}</span>
                                <span className="block text-sm text-slate-500">{place.city} · {place.total > 0 ? `${place.total} listing${place.total === 1 ? '' : 's'}` : 'Browse area'}</span>
                            </span>
                            <ChevronRight className="h-5 w-5 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand" />
                        </button>
                    ))}
                </div>
            </div>
        </section>
    );
}

function ResultRail({ eyebrow, title, icon: Icon, items }) {
    if (!items?.length) return null;
    return (
        <div className="pt-16 first:pt-0">
            <SectionHeading
                eyebrow={<span className="inline-flex items-center gap-1.5"><Icon className="h-4 w-4" />{eyebrow}</span>}
                title={title}
            />
            <div className="-mx-4 mt-8 flex snap-x gap-6 overflow-x-auto px-4 pb-2">
                {items.map((property) => <JustListedCard key={property.id} property={property} />)}
            </div>
        </div>
    );
}

const buildSaveName = (form) => {
    const parts = [];
    if (form.property_type) parts.push(typeLabels[form.property_type]);
    else if (form.bedrooms) parts.push(`${form.bedrooms}+ bed`);
    if (form.suburb) parts.push(`in ${form.suburb}`);
    else if (form.city) parts.push(`in ${form.city}`);
    if (form.max_price) parts.push(`under $${form.max_price}`);
    if (!parts.length) parts.push('All properties');
    return parts.join(' ');
};

export default function MarketplaceIndex({
    properties = [], total = 0, pagination = {}, cities = [], zones = [],
    filters = {}, featured = [], justListed = [], favouriteIds = NO_IDS, auth,
    marketplace = {}, explore = [], recommended = [], recentlyViewed = [],
}) {
    const [favs, setFavs] = useState(favouriteIds);
    const [saveError, setSaveError] = useState(null);
    const flash = usePage().props.flash || {};

    // The server's favourite ids are authoritative: re-sync after every
    // response so a failed toggle never leaves a stale optimistic heart.
    useEffect(() => setFavs(favouriteIds), [favouriteIds]);
    const [view, setView] = useState(() => {
        const param = new URL(window.location.href).searchParams.get('view');
        if (param === 'list' || param === 'map') return param;
        return 'grid';
    });
    const [quickView, setQuickView] = useState(null);
    const [compareOpen, setCompareOpen] = useState(false);
    const [compareItems, setCompareItems] = useState([]);
    const [loading, setLoading] = useState(false);
    const [form, setForm] = useState({
        q: filters.q || '', city: filters.city || '', zone: filters.zone || '',
        suburb: filters.suburb || '', property_type: filters.property_type || '',
        bedrooms: filters.bedrooms != null ? String(filters.bedrooms) : '',
        bathrooms: filters.bathrooms != null ? String(filters.bathrooms) : '',
        min_price: filters.min_price != null ? String(filters.min_price) : '',
        max_price: filters.max_price != null ? String(filters.max_price) : '',
        furnished: filters.furnished != null ? (filters.furnished ? '1' : '0') : '',
        verified: filters.verified != null ? (filters.verified ? '1' : '0') : '',
        payment_terms: filters.payment_terms || '',
        security_type: filters.security_type || '',
        parking_type: filters.parking_type || '',
        preferred_tenant: filters.preferred_tenant || '',
        availability: filters.availability || '',
        amenities: filters.amenities || [],
        sort: typeof filters.sort === 'string' ? filters.sort : 'newest', page: pagination.current_page || 1,
    });
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [moreOpen, setMoreOpen] = useState(false);
    const [mobileNav, setMobileNav] = useState(false);
    const priceTimer = useRef();

    const compareEnabled = Boolean(marketplace.compareEnabled);
    const compareMax = Number(marketplace.compareMax || 3);

    useEffect(() => {
        // Only the marketplace's own partial reloads (filters/sort/page/view)
        // toggle the in-place loading state, so favouriting or navigating away
        // never flashes the results skeleton. `only` marks those visits.
        const isMarketplaceReload = (visit) => Array.isArray(visit?.only) && visit.only.includes('properties');
        const offStart = router.on('start', (event) => {
            if (isMarketplaceReload(event.detail?.visit)) setLoading(true);
        });
        const offFinish = router.on('finish', () => setLoading(false));
        return () => { offStart(); offFinish(); };
    }, []);

    useEffect(() => {
        if (!quickView) return undefined;
        const onKey = (e) => e.key === 'Escape' && setQuickView(null);
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [quickView]);

    const buildParams = (nextForm, nextView) => {
        const source = nextForm || form;
        const { page, ...rest } = source;
        const params = {};
        Object.entries(rest).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                if (value.length) params[key] = value;
            } else if (value !== '' && value !== null && value !== undefined) {
                params[key] = value;
            }
        });
        params.view = nextView || view;
        if (page > 1) params.page = page;
        return params;
    };

    const apply = (merge) => {
        const next = { ...form, ...merge };
        setForm(next);
        // Live, in-place update (like Livewire): only the result props are
        // fetched, the URL updates, and the scroll position is kept so the page
        // never jumps or feels like a full reload.
        router.get(route('home'), buildParams(next, view), {
            replace: true, preserveState: true, preserveScroll: true,
            only: PARTIAL_PROPS,
        });
    };

    const liveSelect = (key) => (e) => apply({ [key]: e.target.value, page: 1 });
    const livePrice = (key) => (e) => {
        const value = e.target.value;
        setForm((f) => ({ ...f, [key]: value }));
        clearTimeout(priceTimer.current);
        priceTimer.current = setTimeout(() => apply({ [key]: value, page: 1 }), 350);
    };

    const toggleAmenity = (key) => {
        const has = form.amenities.includes(key);
        apply({ amenities: has ? form.amenities.filter((a) => a !== key) : [...form.amenities, key], page: 1 });
    };

    const resetFilters = () => {
        setForm({ q: '', city: '', zone: '', suburb: '', property_type: '', bedrooms: '', bathrooms: '', min_price: '', max_price: '', furnished: '', verified: '', payment_terms: '', security_type: '', parking_type: '', preferred_tenant: '', availability: '', amenities: [], sort: 'newest', page: 1 });
        router.get(route('home'), { view }, { replace: true, preserveState: true, preserveScroll: true, only: PARTIAL_PROPS });
    };

    const changeView = (next) => {
        if (next === view) return;
        setView(next);
        router.get(route('home'), buildParams(form, next), {
            replace: true, preserveState: true, preserveScroll: true,
            only: PARTIAL_PROPS,
        });
    };

    const goToPage = (page) => {
        if (page < 1 || page > (pagination.last_page || 1)) return;
        apply({ page });
        document.getElementById('properties')?.scrollIntoView({ behavior: 'smooth' });
    };

    const toggleFavourite = (propertyId) => {
        if (!auth?.user) return router.get(route('login'));
        setFavs((current) => current.includes(propertyId) ? current.filter((id) => id !== propertyId) : [...current, propertyId]);
        router.post(route('tenant.favourites.toggle', propertyId), {}, { preserveScroll: true, preserveState: true });
    };

    const toggleCompare = (property) => {
        const exists = compareItems.find((p) => p.id === property.id);
        if (exists) {
            setCompareItems((items) => items.filter((p) => p.id !== property.id));
            return;
        }
        if (compareItems.length >= compareMax) {
            setCompareOpen(true);
            return;
        }
        setCompareItems((items) => [...items, property]);
    };

    const saveSearch = () => {
        if (!auth?.user) return router.get(route('login'));
        const name = window.prompt('Name this saved search', buildSaveName(form));
        if (name === null) return;
        const { page, view: _view, q, ...criteria } = buildParams(form, view);
        setSaveError(null);
        router.post(route('tenant.saved-searches.store'), {
            name: name || buildSaveName(form),
            criteria,
            notify: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => setSaveError(Object.values(errors)[0] || 'Your search could not be saved.'),
        });
    };

    const isFavourited = (id) => favs.includes(id);
    const popularThreshold = marketplace.popular;
    const activeFilters = ['city', 'zone', 'suburb', 'property_type', 'bedrooms', 'bathrooms', 'min_price', 'max_price', 'furnished', 'verified', 'payment_terms', 'security_type', 'parking_type', 'preferred_tenant', 'availability', 'amenities', 'q']
        .filter((key) => Array.isArray(form[key])
            ? form[key].length > 0
            : form[key] !== '' && form[key] != null).length;
    const activeTier = form.max_price ? String(form.max_price) : '';
    // Only a genuinely featured listing may fill the "Featured this week" showcase.
    const heroProperty = featured?.find((property) => property.featured) || null;
    const pageTotal = pagination.last_page || 1;
    const currentPage = pagination.current_page || 1;
    // Compact, windowed pager: 1 … (cur-1) cur (cur+1) … last, with '…' markers
    // so a 250-page result never prints 250 buttons.
    const pageNumbers = (() => {
        const window = 1; // pages on each side of the current page
        const pages = new Set([1, pageTotal]);
        for (let p = currentPage - window; p <= currentPage + window; p += 1) {
            if (p >= 1 && p <= pageTotal) pages.add(p);
        }
        const sorted = [...pages].sort((a, b) => a - b);
        const out = [];
        let prev = 0;
        for (const p of sorted) {
            if (p - prev > 1) out.push(`gap-${p}`);
            out.push(p);
            prev = p;
        }
        return out;
    })();
    const hasResults = properties.length > 0;
    const isTenant = Boolean(auth?.user?.roles?.some((role) => role.name === 'Tenant'));
    // Favourites are tenant-only: guests are sent to sign in, other roles see no heart.
    const favouriteHandler = !auth?.user || isTenant ? toggleFavourite : null;
    const quickViewHandler = marketplace.quickViewEnabled === false ? null : setQuickView;
    const amenityOptions = Object.entries(marketplace.amenities || {});
    const mapEnabled = Boolean(marketplace.mapEnabled);

    const filterFields = [
        ['city', 'City', <><option value="">Any city</option>{cities.map((city) => <option key={city} value={city}>{city}</option>)}</>],
        ['zone', 'Area', <><option value="">Any area</option>{zones.map((zone) => <option key={zone} value={zone}>{zone}</option>)}</>],
        ['bedrooms', 'Bedrooms', <><option value="">Any</option><option value="0">Studio / 0</option>{bedroomOptions.filter((n) => n !== '0').map((n) => <option key={n} value={n}>{n}+ beds</option>)}</>],
        ['bathrooms', 'Bathrooms', <><option value="">Any</option>{bathroomOptions.map((n) => <option key={n} value={n}>{n}+ baths</option>)}</>],
        ['payment_terms', 'Payment', <><option value="">Any</option>{Object.entries(PAYMENT_TERM_LABELS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</>],
        ['furnished', 'Furnishing', <><option value="">Any</option><option value="1">Furnished</option><option value="0">Unfurnished</option></>],
    ];

    const [mapActive, setMapActive] = useState({ id: null, from: null });
    const dashboardHref = auth?.user ? route(dashboardRouteFor(auth.user.roles)) : route('login');
    // Guests listing a property go straight to the owner-signup flow.
    const listPropertyHref = auth?.user ? dashboardHref : route('register', { as: 'owner' });
    const navLinks = [
        ['#properties', 'Browse homes'],
        ...(explore?.length ? [['#neighbourhoods', 'Neighbourhoods']] : []),
        ['#how-it-works', 'How it works'],
        [route('help.index'), 'Help'],
    ];
    const heroStats = [
        [total.toLocaleString(), total === 1 ? 'Home available' : 'Homes available'],
        [cities.length.toLocaleString(), cities.length === 1 ? 'City covered' : 'Cities covered'],
        ['0%', 'Agent commission'],
    ];
    const views = [
        ['grid', 'Grid', LayoutGrid],
        ['list', 'List', List],
        ...(mapEnabled ? [['map', 'Map', MapIcon]] : []),
    ];
    const hasRails = (marketplace.recommendations && (recommended.length > 0 || recentlyViewed.length > 0)) || justListed.length > 0;

    return (
        <>
            <Head title="Find a Home Without Agent Fees" />
            <div className="min-h-screen bg-white text-slate-900">
                <header className="sticky top-0 z-50 border-b border-white/10 bg-brand text-white">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                        <Link href={route('home')} aria-label="ZimRent home"><Brand dark size={32} /></Link>
                        <nav className="hidden items-center gap-8 md:flex">
                            {navLinks.map(([href, label]) => (
                                <a key={href} href={href} className="text-sm font-medium text-white/75 transition-colors hover:text-white">{label}</a>
                            ))}
                        </nav>
                        <div className="hidden items-center gap-5 sm:flex">
                            {auth?.user ? (
                                <Link href={dashboardHref} className={cn(ctaPitch, 'h-9 px-4 text-sm')}>My dashboard</Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className="text-sm font-medium text-white/75 transition-colors hover:text-white">Sign in</Link>
                                    <Link href={listPropertyHref} className={cn(ctaPitch, 'h-9 px-4 text-sm')}>List your property</Link>
                                </>
                            )}
                        </div>
                        <button className="grid h-10 w-10 place-items-center rounded-full text-white hover:bg-white/10 sm:hidden" onClick={() => setMobileNav((v) => !v)} aria-label="Toggle navigation" aria-expanded={mobileNav}>
                            {mobileNav ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                    {mobileNav && (
                        <div className="border-t border-white/10 px-4 pb-4 pt-2 sm:hidden">
                            {navLinks.map(([href, label]) => (
                                <a key={href} href={href} onClick={() => setMobileNav(false)} className="block border-b border-white/10 py-3.5 text-[15px] text-white">{label}</a>
                            ))}
                            {!auth?.user && <Link href={route('login')} className="block border-b border-white/10 py-3.5 text-[15px] text-white">Sign in</Link>}
                            <Link href={listPropertyHref} className={cn(ctaPitch, 'mt-4 h-11 w-full text-sm')}>{auth?.user ? 'My dashboard' : 'List your property'}</Link>
                        </div>
                    )}
                    {/* Housing categories for signed-in tenants — quick one-tap type filters, top right. */}
                    {isTenant && (
                        <div className="border-t border-white/10">
                            <div className="mx-auto flex max-w-7xl items-center justify-end gap-1.5 overflow-x-auto px-4 py-2 sm:px-6 lg:px-8">
                                <span className="mr-1 hidden shrink-0 text-[11px] font-bold uppercase tracking-wider text-white/50 sm:block">Categories</span>
                                <button
                                    type="button"
                                    onClick={() => { apply({ property_type: '', page: 1 }); document.getElementById('properties')?.scrollIntoView({ behavior: 'smooth' }); }}
                                    className={cn('shrink-0 rounded-full px-3 py-1 text-xs font-semibold transition-colors', !form.property_type ? 'bg-pitch text-brand' : 'bg-white/10 text-white/80 hover:bg-white/20 hover:text-white')}
                                >
                                    All
                                </button>
                                {Object.entries(typeLabels).map(([key, label]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => { apply({ property_type: key, page: 1 }); document.getElementById('properties')?.scrollIntoView({ behavior: 'smooth' }); }}
                                        className={cn('shrink-0 rounded-full px-3 py-1 text-xs font-semibold transition-colors', form.property_type === key ? 'bg-pitch text-brand' : 'bg-white/10 text-white/80 hover:bg-white/20 hover:text-white')}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </header>

                <section className="relative overflow-hidden bg-brand text-white">
                    <div aria-hidden className="pointer-events-none absolute -right-48 -top-48 h-[40rem] w-[40rem] rounded-full border border-white/[.07]" />
                    <div aria-hidden className="pointer-events-none absolute -right-24 -top-24 h-[28rem] w-[28rem] rounded-full border border-white/[.07]" />
                    <div aria-hidden className="pointer-events-none absolute -bottom-40 -left-40 h-[26rem] w-[26rem] rounded-full border border-white/[.07]" />

                    <div className={cn('relative mx-auto grid max-w-7xl items-center gap-12 px-4 pb-20 pt-14 sm:px-6 sm:pt-20 lg:px-8 lg:pb-24', heroProperty && 'lg:grid-cols-[1.1fr_.9fr]')}>
                        <div>
                            <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-semibold text-pitch ring-1 ring-white/15">
                                <span className="h-1.5 w-1.5 rounded-full bg-pitch" /> Direct from owners · No agent commission
                            </span>
                            <h1 className="mt-6 text-balance text-5xl font-semibold leading-[1.03] tracking-tight sm:text-6xl xl:text-7xl">
                                Find your next home.
                                <span className="block text-pitch">Rent from the owner.</span>
                            </h1>
                            <p className="mt-6 max-w-xl text-lg leading-8 text-white/70">
                                Beautiful homes, verified owners and transparent renting — without the unnecessary agent commission.
                            </p>
                            <HeroSearch initial={form.q} onSubmit={(q) => apply({ q, page: 1 })} suggestionUrl={marketplace.suggestionUrl} />
                            <div className="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/75">
                                {['Verified listings', 'No agent commission', 'Direct owner contact'].map((item) => (
                                    <span key={item} className="inline-flex items-center gap-1.5"><CheckCircle2 className="h-4 w-4 text-pitch" />{item}</span>
                                ))}
                            </div>
                            <dl className="mt-12 grid max-w-lg grid-cols-3 border-t border-white/15 pt-6">
                                {heroStats.map(([value, label], i) => (
                                    <div key={label} className={cn(i > 0 && 'border-l border-white/15 pl-5')}>
                                        <dd className="text-3xl font-semibold tracking-tight sm:text-4xl">{value}</dd>
                                        <dt className="mt-1 text-xs text-white/60 sm:text-sm">{label}</dt>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        {heroProperty && (
                            <article className="overflow-hidden rounded-3xl bg-white text-slate-900 shadow-[0_30px_60px_-20px_rgba(0,0,0,0.45)]">
                                <div className="relative aspect-[4/3] bg-slate-100">
                                    <Link href={route('property.show', heroProperty.id)} className="absolute inset-0" tabIndex={-1}>
                                        <PropertyMedia property={heroProperty} className="h-full w-full" />
                                    </Link>
                                    <div className="absolute left-3 top-3 flex flex-wrap gap-1.5">
                                        <span className={tag}><Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" /> Featured this week</span>
                                        {heroProperty.verified && <span className={tag}><BadgeCheck className="h-3.5 w-3.5 text-brand" /> Verified</span>}
                                    </div>
                                </div>
                                <div className="p-6">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h2 className="truncate text-xl font-semibold tracking-tight">{heroProperty.title}</h2>
                                            <p className="mt-0.5 truncate text-sm text-slate-500">{locationOf(heroProperty)}</p>
                                        </div>
                                        <OwnerTrustBadge owner={heroProperty.owner} className="shrink-0" />
                                    </div>
                                    <p className="mt-1 truncate text-sm text-slate-500">{specsOf(heroProperty)}</p>
                                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                                        <Price property={heroProperty} className="text-xl" />
                                        <div className="flex gap-2">
                                            <Link href={auth?.user ? route('property.show', heroProperty.id) : route('login')} className="btn-secondary h-10 px-4 text-sm">Contact</Link>
                                            <Link href={route('property.show', heroProperty.id)} className="btn-primary h-10 px-4 text-sm">View home</Link>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        )}
                    </div>
                </section>

                <section id="properties" className="scroll-mt-16 bg-white">
                    <div className={cn('mx-auto px-4 pb-20 pt-16 sm:px-6 lg:px-8', view === 'map' ? 'max-w-[1600px]' : 'max-w-7xl')}>
                        <SectionHeading eyebrow="Marketplace" title="Browse homes." />

                        <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                            <button type="button" onClick={() => setFiltersOpen((v) => !v)} aria-expanded={filtersOpen} className="flex w-full items-center justify-between text-sm font-semibold text-slate-900 lg:hidden">
                                <span className="inline-flex items-center gap-2">
                                    <SlidersHorizontal className="h-4 w-4" /> Filters
                                    {activeFilters > 0 && <span className="grid h-5 min-w-5 place-items-center rounded-full bg-brand px-1.5 text-[11px] text-white">{activeFilters}</span>}
                                </span>
                                <ChevronDown className={cn('h-4 w-4 transition-transform', filtersOpen && 'rotate-180')} />
                            </button>

                            <div className={cn(!filtersOpen && 'hidden lg:block', filtersOpen && 'mt-4 lg:mt-0')}>
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="-mx-1 flex max-w-full gap-2 overflow-x-auto px-1 pb-1">
                                        <button type="button" aria-pressed={!form.property_type} onClick={() => apply({ property_type: '', page: 1 })} className={cn('chip', !form.property_type && 'chip-active')}>
                                            All homes
                                        </button>
                                        {Object.entries(typeLabels).map(([key, label]) => {
                                            const Icon = typeIcons[key] || Home;
                                            const active = form.property_type === key;
                                            return (
                                                <button key={key} type="button" aria-pressed={active} onClick={() => apply({ property_type: active ? '' : key, page: 1 })} className={cn('chip', active && 'chip-active')}>
                                                    <Icon className="h-4 w-4" /> {label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                    <label className="flex w-full items-center gap-2 rounded-full border border-slate-300 px-4 transition focus-within:border-brand focus-within:ring-4 focus-within:ring-brand/10 md:ml-auto md:w-72">
                                        <Search className="h-4 w-4 text-slate-400" />
                                        <input
                                            value={form.q}
                                            onChange={(e) => setForm((f) => ({ ...f, q: e.target.value }))}
                                            onKeyDown={(e) => e.key === 'Enter' && apply({ q: e.target.value, page: 1 })}
                                            placeholder="Keyword"
                                            className="h-10 w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400"
                                            aria-label="Search by keyword"
                                        />
                                    </label>
                                </div>

                                <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    {filterFields.map(([key, label, options]) => (
                                        <label key={key} className="block">
                                            <span className="field-caption">{label}</span>
                                            <select value={form[key]} onChange={liveSelect(key)} className="select-field">{options}</select>
                                        </label>
                                    ))}
                                    <label className="block">
                                        <span className="field-caption">Min rent</span>
                                        <input type="number" min="0" placeholder="$300" value={form.min_price} onChange={livePrice('min_price')} className="input-field" />
                                    </label>
                                    <label className="block">
                                        <span className="field-caption">Max rent</span>
                                        <input type="number" min="0" placeholder="$800" value={form.max_price} onChange={livePrice('max_price')} className="input-field" />
                                    </label>
                                </div>

                                {moreOpen && (
                                    <div className="mt-4 grid gap-3 border-t border-slate-200 pt-4 md:grid-cols-3">
                                        {[
                                            ['verified', 'Verified listings', <><option value="">Any</option><option value="1">Verified only</option><option value="0">Not verified</option></>],
                                            ['availability', 'Availability', <><option value="">Any</option><option value="now">Available now</option><option value="upcoming">Available soon</option></>],
                                            ['security_type', 'Security', <><option value="">Any</option>{Object.entries(SECURITY_TYPES).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</>],
                                            ['parking_type', 'Parking', <><option value="">Any</option>{Object.entries(PARKING_TYPES).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</>],
                                            ['preferred_tenant', 'Preferred tenant', <><option value="">Anyone</option>{Object.entries(PREFERRED_TENANTS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</>],
                                        ].map(([key, label, options]) => (
                                            <label key={key} className="block">
                                                <span className="field-caption">{label}</span>
                                                <select value={form[key]} onChange={liveSelect(key)} className="select-field">{options}</select>
                                            </label>
                                        ))}
                                        <div className="flex items-end">
                                            <button type="button" onClick={() => setMoreOpen(false)} className="btn-primary h-11 w-full text-sm">Done</button>
                                        </div>
                                        <div className="md:col-span-3">
                                            <span className="field-caption">Amenities</span>
                                            {amenityOptions.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {amenityOptions.map(([key, label]) => (
                                                        <button key={key} type="button" aria-pressed={form.amenities.includes(key)} onClick={() => toggleAmenity(key)} className={cn('chip', form.amenities.includes(key) && 'chip-active')}>
                                                            {label}
                                                        </button>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-sm text-slate-400">No amenity filters configured yet.</p>
                                            )}
                                        </div>
                                    </div>
                                )}

                                <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-4">
                                    <button type="button" onClick={() => setMoreOpen((v) => !v)} aria-expanded={moreOpen} className="chip">
                                        <SlidersHorizontal className="h-4 w-4" /> {moreOpen ? 'Fewer filters' : 'More filters'}
                                    </button>
                                    <span className="mx-2 hidden h-5 w-px bg-slate-200 sm:block" />
                                    {priceTiers.map(([value, label]) => (
                                        <button key={value} type="button" aria-pressed={activeTier === value} onClick={() => apply({ max_price: activeTier === value ? '' : value, page: 1 })} className={cn('chip', activeTier === value && 'chip-active')}>{label}</button>
                                    ))}
                                    {activeFilters > 0 && (
                                        <button type="button" onClick={resetFilters} className="ml-auto inline-flex items-center gap-1.5 px-2 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                                            <RotateCcw className="h-4 w-4" /> Clear all
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>

                        <p className="mt-4 flex items-start gap-2 text-sm text-slate-500">
                            <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-brand" />
                            Every home is listed directly by its owner. Never send money or personal documents before viewing the property and meeting the owner.
                        </p>

                        <div className="mt-10 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
                            <div>
                                <p className="text-xl font-semibold tracking-tight text-slate-900">{total.toLocaleString()} home{total === 1 ? '' : 's'}</p>
                                <p className="text-sm text-slate-500">{activeFilters > 0 ? `${activeFilters} filter${activeFilters === 1 ? '' : 's'} applied` : 'Rented directly by their owners'}</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {isTenant && (
                                    <button type="button" onClick={saveSearch} className="chip">
                                        <Save className="h-4 w-4" /> Save search
                                    </button>
                                )}
                                <select value={form.sort} onChange={liveSelect('sort')} aria-label="Sort properties" className="select-field h-10 w-auto rounded-full">
                                    <option value="newest">Newest first</option>
                                    <option value="recently_updated">Recently updated</option>
                                    <option value="price_asc">Price: low to high</option>
                                    <option value="price_desc">Price: high to low</option>
                                    <option value="price_per_m2">Best value per m²</option>
                                    <option value="featured">Featured first</option>
                                    <option value="top_rated">Top rated</option>
                                </select>
                                <div className="flex rounded-full bg-slate-100 p-1" role="group" aria-label="Result layout">
                                    {views.map(([key, label, Icon]) => (
                                        <button key={key} type="button" onClick={() => changeView(key)} aria-pressed={view === key} className={cn('inline-flex h-8 items-center gap-1.5 rounded-full px-3.5 text-sm font-medium transition', view === key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900')}>
                                            <Icon className="h-4 w-4" /> <span className="hidden sm:inline">{label}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {(flash.error || saveError) && (
                            <p role="alert" className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
                                {flash.error || saveError}
                            </p>
                        )}
                        {flash.success && (
                            <p role="status" className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                                {flash.success}
                            </p>
                        )}

                        <div className="mt-8">
                            {!hasResults && !loading && (
                                <EmptyState
                                    icon={Search}
                                    title={activeFilters > 0 ? "We couldn't find an exact match" : 'No properties are listed yet'}
                                    description={activeFilters > 0
                                        ? 'Small tweaks often surface great homes you might have missed. Try relaxing a filter or two.'
                                        : 'New homes are listed every week. Check back soon.'}
                                    action={activeFilters > 0 && (
                                        <div className="flex flex-wrap justify-center gap-2">
                                            <Button onClick={resetFilters}><RotateCcw className="h-4 w-4" /> Clear all filters</Button>
                                            <Button variant="outline" onClick={() => apply({ max_price: '', page: 1 })}>Lower the budget</Button>
                                            <Button variant="outline" onClick={() => apply({ city: '', zone: '', page: 1 })}>Any location</Button>
                                        </div>
                                    )}
                                />
                            )}

                            {loading ? (
                                <SkeletonGrid />
                            ) : hasResults && view === 'map' && mapEnabled ? (
                                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)]">
                                    <div className="order-2 space-y-1 lg:order-1 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:pr-2">
                                        {properties.map((property) => (
                                            <MapListItem
                                                key={property.id}
                                                property={property}
                                                active={mapActive.id === property.id}
                                                scrollIntoView={mapActive.from === 'map'}
                                                onHover={(id) => setMapActive({ id, from: 'list' })}
                                                isFavourited={isFavourited(property.id)}
                                                onToggleFavourite={favouriteHandler}
                                            />
                                        ))}
                                    </div>
                                    <div className="order-1 h-[60vh] overflow-hidden rounded-2xl border border-slate-200 lg:sticky lg:top-20 lg:order-2 lg:h-[calc(100vh-6rem)]">
                                        <MapResults
                                            properties={properties}
                                            total={total}
                                            activeId={mapActive.id}
                                            onActivate={(id, from) => setMapActive({ id, from })}
                                            center={marketplace.mapCenter}
                                        />
                                    </div>
                                </div>
                            ) : hasResults ? (
                                view === 'list' ? (
                                    <div className="grid gap-6">
                                        {properties.map((property) => (
                                            <PropertyListRow
                                                key={property.id}
                                                property={property}
                                                threshold={popularThreshold}
                                                compareEnabled={compareEnabled}
                                                compareSelected={compareItems.some((p) => p.id === property.id)}
                                                onToggleCompare={toggleCompare}
                                                isFavourited={isFavourited(property.id)}
                                                onToggleFavourite={favouriteHandler}
                                                onQuickView={quickViewHandler}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                                        {properties.map((property) => (
                                            <PropertyCard
                                                key={property.id}
                                                property={property}
                                                threshold={popularThreshold}
                                                compareEnabled={compareEnabled}
                                                compareSelected={compareItems.some((p) => p.id === property.id)}
                                                onToggleCompare={toggleCompare}
                                                isFavourited={isFavourited(property.id)}
                                                onToggleFavourite={favouriteHandler}
                                                onQuickView={quickViewHandler}
                                            />
                                        ))}
                                    </div>
                                )
                            ) : null}

                            {hasResults && !loading && pageTotal > 1 && (
                                <nav className="mt-14 flex items-center justify-center gap-1" aria-label="Pagination">
                                    <button type="button" disabled={pagination.current_page <= 1} onClick={() => goToPage(pagination.current_page - 1)} aria-label="Previous page" className="grid h-10 w-10 place-items-center rounded-full text-slate-900 hover:bg-slate-100 disabled:pointer-events-none disabled:text-slate-300">
                                        <ChevronLeft className="h-5 w-5" />
                                    </button>
                                    {pageNumbers.map((num) => (
                                        typeof num === 'string' ? (
                                            <span key={num} className="grid h-10 w-10 place-items-center text-sm text-slate-400" aria-hidden="true">…</span>
                                        ) : (
                                            <button key={num} type="button" onClick={() => goToPage(num)} aria-current={num === currentPage ? 'page' : undefined} className={cn('grid h-10 min-w-10 place-items-center rounded-full px-2 text-sm font-medium transition', num === currentPage ? 'pointer-events-none bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100')}>{num}</button>
                                        )
                                    ))}
                                    <button type="button" disabled={pagination.current_page >= pageTotal} onClick={() => goToPage(pagination.current_page + 1)} aria-label="Next page" className="grid h-10 w-10 place-items-center rounded-full text-slate-900 hover:bg-slate-100 disabled:pointer-events-none disabled:text-slate-300">
                                        <ChevronRight className="h-5 w-5" />
                                    </button>
                                </nav>
                            )}
                        </div>
                    </div>

                    {hasResults && !loading && view !== 'map' && compareItems.length > 1 && (
                        <div className="fixed inset-x-0 bottom-0 z-[60] px-4 pb-4 animate-fade-up">
                            <div className="mx-auto flex max-w-xl items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-3.5 shadow-[0_10px_40px_rgba(0,0,0,0.15)]">
                                <div className="flex items-center gap-3">
                                    <ArrowLeftRight className="h-5 w-5 text-brand" />
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">Comparing {compareItems.length} propert{compareItems.length === 1 ? 'y' : 'ies'}</p>
                                        <p className="text-xs text-slate-500">Tap “Compare” again to remove one</p>
                                    </div>
                                </div>
                                <button type="button" onClick={() => setCompareOpen(true)} className="btn-primary h-10 px-5 text-sm">Compare now</button>
                            </div>
                        </div>
                    )}
                </section>

                {hasRails && (
                    <section className="bg-slate-100">
                        <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                            {marketplace.recommendations && <ResultRail eyebrow="Picked for you" icon={Sparkles} title="Recommended for you." items={recommended} />}
                            {marketplace.recommendations && <ResultRail eyebrow="Your trail" icon={History} title="Recently viewed." items={recentlyViewed} />}
                            <ResultRail eyebrow="Fresh on the market" icon={Zap} title="Just listed." items={justListed} />
                        </div>
                    </section>
                )}

                <ExploreNeighbourhoods places={explore} onPick={(place) => { apply({ suburb: place.suburb, city: place.city, page: 1 }); document.getElementById('properties')?.scrollIntoView({ behavior: 'smooth' }); }} />

                <section id="how-it-works" className="scroll-mt-16 bg-slate-100">
                    <div className="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8">
                        <div className="max-w-2xl">
                            <p className="text-sm font-semibold text-brand">How it works</p>
                            <h2 className="mt-1 text-balance text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">A better rental experience, from search to keys.</h2>
                            <p className="mt-4 text-lg leading-8 text-slate-500">ZimRent removes the unnecessary middle layer and gives owners and tenants a cleaner way to connect.</p>
                        </div>
                        <div className="mt-14 grid gap-4 md:grid-cols-3">
                            {[
                                { icon: Home, title: 'Owners list directly', copy: 'Create a polished listing, add your property details and reach people actively looking for a home.' },
                                { icon: Search, title: 'Tenants search freely', copy: 'Compare real listings, filter by what matters and connect directly with the property owner.' },
                                { icon: BadgeDollarSign, title: 'Keep costs simple', copy: 'Owners use an affordable subscription instead of giving away a large commission to an agent.' },
                            ].map(({ icon: Icon, title, copy }, i) => (
                                <div key={title} className="rounded-3xl bg-white p-8">
                                    <div className="flex items-center justify-between">
                                        <Icon className="h-7 w-7 text-brand" strokeWidth={1.8} />
                                        <span className="text-sm font-semibold text-slate-300">0{i + 1}</span>
                                    </div>
                                    <h3 className="mt-10 text-xl font-semibold tracking-tight text-slate-900">{title}</h3>
                                    <p className="mt-2 text-[15px] leading-7 text-slate-500">{copy}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="bg-brand text-white">
                    <div className="mx-auto flex max-w-7xl flex-col items-start justify-between gap-8 px-4 py-20 sm:px-6 md:flex-row md:items-center lg:px-8">
                        <div className="max-w-2xl">
                            <KeyRound className="h-8 w-8 text-pitch" strokeWidth={1.8} />
                            <h2 className="mt-5 text-balance text-4xl font-semibold tracking-tight sm:text-5xl">Own a property? List it and keep the commission.</h2>
                            <p className="mt-4 text-lg leading-8 text-white/70">Reach tenants who are actively searching, manage enquiries and viewings in one place, and pay a simple subscription instead of an agent's cut.</p>
                        </div>
                        <div className="flex shrink-0 flex-wrap gap-3">
                            <Link href={listPropertyHref} className="inline-flex h-12 items-center gap-2 rounded-full bg-pitch px-7 text-[15px] font-semibold text-brand transition hover:bg-white">
                                {auth?.user ? 'Go to my dashboard' : 'List your property'} <ArrowRight className="h-4 w-4" />
                            </Link>
                            <a href="#properties" className="inline-flex h-12 items-center rounded-full border border-white/30 px-7 text-[15px] font-semibold text-white transition hover:bg-white/10">Browse homes</a>
                        </div>
                    </div>
                </section>

                <footer className="bg-slate-100 text-sm">
                    <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <div className="flex flex-col justify-between gap-8 border-b border-slate-300 pb-8 md:flex-row md:items-center">
                            <div>
                                <Link href={route('home')}><Brand size={30} /></Link>
                                <p className="mt-3 max-w-sm text-slate-500">A simpler way to rent — directly from owners, with less friction and more transparency.</p>
                            </div>
                            <div className="flex flex-wrap gap-x-8 gap-y-3 text-slate-600">
                                {auth?.user ? <Link href={dashboardHref} className="hover:text-slate-900 hover:underline">My dashboard</Link> : <Link href={route('login')} className="hover:text-slate-900 hover:underline">Sign in</Link>}
                                <Link href={route('help.show', 'owner')} className="hover:text-slate-900 hover:underline">Owner guide</Link>
                                <Link href={route('help.show', 'tenant')} className="hover:text-slate-900 hover:underline">Tenant guide</Link>
                                <Link href={route('help.index')} className="hover:text-slate-900 hover:underline">Help Center</Link>
                            </div>
                        </div>
                        <p className="pt-6 text-xs text-slate-500">© {new Date().getFullYear()} ZimRent. Rent directly. Live simply.</p>
                    </div>
                </footer>
            </div>

            {compareItems.length > 1 && compareOpen && (
                <ComparePanel
                    items={compareItems}
                    onClose={() => setCompareOpen(false)}
                    onRemove={(id) => setCompareItems((items) => items.filter((p) => p.id !== id))}
                />
            )}

            {quickView && (
                <QuickViewPanel
                    property={quickView}
                    onClose={() => setQuickView(null)}
                    isFavourited={isFavourited(quickView.id)}
                    onToggleFavourite={favouriteHandler}
                />
            )}

            <ChatWidget />
        </>
    );
}
