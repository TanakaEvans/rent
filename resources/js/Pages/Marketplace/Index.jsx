import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {
    Search, MapPin, BedDouble, Bath, Armchair, BadgeCheck, Star,
    ShieldCheck, Landmark, BadgeDollarSign, ArrowRight, Warehouse,
    Home, Building2, ChevronDown, SlidersHorizontal, Heart, Sparkles,
    CheckCircle2, Menu, X, ArrowUpRight, LayoutGrid, List, Eye,
    Ruler, UserCheck, RotateCcw, Map as MapIcon, Save, ChevronLeft,
    ChevronRight, ArrowLeftRight, History, Zap,
} from 'lucide-react';
import Brand from '@/Components/Shared/Brand';
import PropertyArt from '@/Components/Shared/PropertyArt';
import EmptyState from '@/Components/Shared/EmptyState';
import { Button, buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import { dashboardRouteFor } from '@/lib/roles';

const typeLabels = {
    house: 'House', flat: 'Flat / Apartment', townhouse: 'Townhouse',
    cottage: 'Cottage', room: 'Room', commercial: 'Commercial', land: 'Land',
};

const typeIcons = {
    house: Home, flat: Building2, townhouse: Building2, cottage: Home,
    room: Warehouse, commercial: Warehouse, land: Landmark,
};

const formatPrice = (value) => '$' + Number(value).toLocaleString();
const priceTiers = [['500', 'Under $500'], ['1000', 'Under $1,000'], ['3000', 'Under $3,000']];
const bedroomOptions = ['0', '1', '2', '3', '4'];
const bathroomOptions = ['1', '2', '3', '4'];

const PARTIAL_PROPS = [
    'properties', 'total', 'pagination', 'cities', 'zones', 'featured', 'justListed', 'filters',
    'marketplace', 'explore', 'recommended', 'recentlyViewed', 'favouriteIds',
];

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
                className="h-full w-full object-cover transition duration-700 group-hover:scale-105"
            />
            {images.length > 1 && (
                <>
                    <button
                        type="button"
                        aria-label="Previous photo"
                        onClick={(e) => { e.preventDefault(); e.stopPropagation(); setIndex((i) => (i - 1 + images.length) % images.length); }}
                        className="absolute left-2.5 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-800 opacity-0 shadow-lg transition group-hover/media:opacity-100 hover:bg-white"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        aria-label="Next photo"
                        onClick={(e) => { e.preventDefault(); e.stopPropagation(); setIndex((i) => (i + 1) % images.length); }}
                        className="absolute right-2.5 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-800 opacity-0 shadow-lg transition group-hover/media:opacity-100 hover:bg-white"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                    <span className="absolute bottom-2.5 right-3 rounded-full bg-black/50 px-2.5 py-1 text-[10px] font-black text-white backdrop-blur">
                        {index + 1} / {images.length}
                    </span>
                    <div className="absolute bottom-2.5 left-3 flex gap-1">
                        {images.map((image, i) => (
                            <span key={image.path} className={cn('h-1.5 w-1.5 rounded-full transition', i === index ? 'bg-white' : 'bg-white/40')} />
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
        <div className="absolute inset-x-0 top-0 flex items-start justify-between p-4">
            <div className="flex flex-wrap gap-2">
                {property.featured && (
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-slate-950/75 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white shadow-xl backdrop-blur-md">
                        <Star className="h-3 w-3 fill-amber-300 text-amber-300" /> Featured
                    </span>
                )}
                {property.owner?.verified && (
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/20 bg-emerald-600/95 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white shadow-xl backdrop-blur-md">
                        <UserCheck className="h-3 w-3" /> Verified Owner
                    </span>
                )}
                {property.verified && (
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/20 bg-emerald-500/90 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white shadow-xl backdrop-blur-md">
                        <BadgeCheck className="h-3 w-3" /> Verified
                    </span>
                )}
                {isPopular(property, threshold) && (
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-orange-500/95 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white shadow-xl backdrop-blur-md">
                        <Zap className="h-3 w-3 fill-amber-200 text-amber-200" /> Popular
                    </span>
                )}
                {isNewListing(property) && (
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-sky-500/90 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white shadow-xl backdrop-blur-md">
                        <Sparkles className="h-3 w-3" /> New
                    </span>
                )}
            </div>
            <button
                type="button"
                onClick={() => onToggleFavourite(property.id)}
                aria-pressed={isFavourited}
                aria-label={isFavourited ? 'Remove from favourites' : 'Save to favourites'}
                className="grid h-10 w-10 place-items-center rounded-full border border-white/20 bg-black/35 text-white backdrop-blur-md transition hover:scale-105 hover:bg-white hover:text-rose-500"
            >
                <Heart className={cn('h-4 w-4', isFavourited && 'fill-current text-rose-500')} />
            </button>
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
            className={cn('inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-xs font-black transition',
                selected ? 'border-emerald-500 bg-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-800')}
        >
            <ArrowLeftRight className="h-3.5 w-3.5" /> {selected ? 'Comparing' : 'Compare'}
        </button>
    );
}

function SpecItem({ icon: Icon, children }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-[13px] font-semibold text-slate-600">
            <Icon className="h-3.5 w-3.5 text-slate-400" /> {children}
        </span>
    );
}

function TrustFeature({ property }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-500">
            <UserCheck className="h-3.5 w-3.5 text-emerald-600" /> Listed directly by the owner
            {property.owner?.verified && <BadgeCheck className="ml-1 h-3.5 w-3.5 text-emerald-600" />}
        </span>
    );
}

function AvailabilityLine({ property }) {
    const now = isAvailableNow(property);
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-[11px] font-bold', now ? 'text-emerald-600' : 'text-amber-600')}>
            <span className={cn('h-1.5 w-1.5 rounded-full', now ? 'animate-pulse bg-emerald-500' : 'bg-amber-500')} />
            {availabilityOf(property)}
        </span>
    );
}

function PropertyCard({ property, threshold, compareEnabled, compareSelected, onToggleCompare, isFavourited, onToggleFavourite, onQuickView }) {
    const type = typeLabels[property.property_type] || property.property_type;
    const area = areaOf(property);
    return (
        <article className="group flex flex-col overflow-hidden rounded-[24px] border border-slate-200/80 bg-white shadow-[0_12px_35px_-22px_rgba(15,23,42,.35)] transition duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-[0_30px_70px_-30px_rgba(15,23,42,.35)]">
            <div className="relative h-56 bg-slate-100">
                <Link href={route('property.show', property.id)} className="block h-full">
                    <PropertyMedia property={property} className="h-full w-full" />
                </Link>
                <PropertyBadges property={property} threshold={threshold} isFavourited={isFavourited} onToggleFavourite={onToggleFavourite} />
                <div className="absolute bottom-4 right-4">
                    <CompareChip enabled={compareEnabled} selected={compareSelected} onToggle={() => onToggleCompare(property)} />
                </div>
            </div>
            <div className="flex flex-1 flex-col p-5 sm:p-6">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="truncate text-[17px] font-black tracking-tight text-slate-900">
                            <Link href={route('property.show', property.id)} className="transition hover:text-emerald-700">{property.title}</Link>
                        </h3>
                        <p className="mt-1 flex items-center gap-1.5 truncate text-[13px] text-slate-500">
                            <MapPin className="h-3.5 w-3.5 shrink-0 text-emerald-500" /> {locationOf(property)}
                        </p>
                    </div>
                    <div className="shrink-0 text-right">
                        <div className="text-2xl font-black tracking-tight text-slate-950">
                            {formatPrice(property.price)}<span className="ml-1 text-[11px] font-bold text-slate-400">/month</span>
                        </div>
                        {property.deposit > 0 && (
                            <div className="mt-0.5 text-[11px] font-medium text-slate-400">+ {formatPrice(property.deposit)} deposit</div>
                        )}
                    </div>
                </div>

                <div className="mt-3">
                    <TrustFeature property={property} />
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <SpecItem icon={typeIcons[property.property_type] || Home}>{type}</SpecItem>
                    {property.bedrooms != null && property.bedrooms > 0 && <SpecItem icon={BedDouble}>{property.bedrooms} bed</SpecItem>}
                    <SpecItem icon={Bath}>{property.bathrooms} bath</SpecItem>
                    {area && <SpecItem icon={Ruler}>{area}</SpecItem>}
                    <SpecItem icon={Armchair}>{property.furnished ? 'Furnished' : 'Unfurnished'}</SpecItem>
                </div>

                <div className="mt-auto border-t border-slate-100 pt-4">
                    <div className="mt-4 flex items-center justify-between gap-3">
                        <AvailabilityLine property={property} />
                        {timeAgo(property.created_at) && (
                            <span className="text-[11px] font-semibold text-slate-400">{timeAgo(property.created_at)}</span>
                        )}
                    </div>
                    <div className="mt-3 flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => onQuickView(property)}
                            className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-full border border-slate-200 bg-white text-xs font-black text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            <Eye className="h-3.5 w-3.5" /> Quick View
                        </button>
                        <Link
                            href={route('property.show', property.id)}
                            className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-full bg-slate-900 text-xs font-black text-white transition hover:bg-emerald-600"
                        >
                            Full details <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </article>
    );
}

function PropertyListRow({ property, threshold, compareEnabled, compareSelected, onToggleCompare, isFavourited, onToggleFavourite, onQuickView }) {
    const type = typeLabels[property.property_type] || property.property_type;
    const area = areaOf(property);
    return (
        <article className="group overflow-hidden rounded-[24px] border border-slate-200/80 bg-white shadow-[0_12px_35px_-22px_rgba(15,23,42,.35)] transition duration-300 hover:border-slate-300 hover:shadow-[0_30px_70px_-30px_rgba(15,23,42,.35)] sm:flex">
            <div className="relative h-56 bg-slate-100 sm:h-auto sm:w-64 sm:shrink-0">
                <Link href={route('property.show', property.id)} className="block h-full">
                    <PropertyMedia property={property} className="h-full w-full" />
                </Link>
                <PropertyBadges property={property} threshold={threshold} isFavourited={isFavourited} onToggleFavourite={onToggleFavourite} />
                <div className="absolute bottom-4 right-4">
                    <CompareChip enabled={compareEnabled} selected={compareSelected} onToggle={() => onToggleCompare(property)} />
                </div>
            </div>
            <div className="flex flex-1 flex-col p-5 sm:p-6">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="truncate text-lg font-black tracking-tight text-slate-900">
                            <Link href={route('property.show', property.id)} className="transition hover:text-emerald-700">{property.title}</Link>
                        </h3>
                        <p className="mt-1 flex items-center gap-1.5 truncate text-[13px] text-slate-500">
                            <MapPin className="h-3.5 w-3.5 shrink-0 text-emerald-500" /> {locationOf(property)}
                        </p>
                    </div>
                    <div className="shrink-0 text-right">
                        <div className="text-2xl font-black tracking-tight text-slate-950">
                            {formatPrice(property.price)}<span className="ml-1 text-[11px] font-bold text-slate-400">/month</span>
                        </div>
                        {property.deposit > 0 && (
                            <div className="mt-0.5 text-[11px] font-medium text-slate-400">+ {formatPrice(property.deposit)} deposit</div>
                        )}
                    </div>
                </div>

                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                    <TrustFeature property={property} />
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <SpecItem icon={typeIcons[property.property_type] || Home}>{type}</SpecItem>
                    {property.bedrooms != null && property.bedrooms > 0 && <SpecItem icon={BedDouble}>{property.bedrooms} bed</SpecItem>}
                    <SpecItem icon={Bath}>{property.bathrooms} bath</SpecItem>
                    {area && <SpecItem icon={Ruler}>{area}</SpecItem>}
                    <SpecItem icon={Armchair}>{property.furnished ? 'Furnished' : 'Unfurnished'}</SpecItem>
                </div>

                <div className="mt-auto border-t border-slate-100 pt-4">
                    <div className="mt-4 flex items-center justify-between gap-3">
                        <AvailabilityLine property={property} />
                        {timeAgo(property.created_at) && (
                            <span className="text-[11px] font-semibold text-slate-400">{timeAgo(property.created_at)}</span>
                        )}
                    </div>
                    <div className="mt-3 flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => onQuickView(property)}
                            className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-full border border-slate-200 bg-white text-xs font-black text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            <Eye className="h-3.5 w-3.5" /> Quick View
                        </button>
                        <Link
                            href={route('property.show', property.id)}
                            className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-full bg-slate-900 text-xs font-black text-white transition hover:bg-emerald-600"
                        >
                            Full details <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </article>
    );
}

function JustListedCard({ property }) {
    const type = typeLabels[property.property_type] || property.property_type;
    return (
        <Link
            href={route('property.show', property.id)}
            className="w-[240px] shrink-0 snap-start overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-[0_22px_50px_-25px_rgba(15,23,42,.4)]"
        >
            <div className="h-36 bg-slate-100">
                <PropertyMedia property={property} className="h-full w-full" />
            </div>
            <div className="p-4">
                <div className="text-lg font-black tracking-tight text-slate-950">
                    {formatPrice(property.price)}<span className="ml-1 text-[11px] font-bold text-slate-400">/month</span>
                </div>
                <h4 className="mt-1 truncate text-sm font-bold text-slate-900">{property.title}</h4>
                <p className="mt-0.5 truncate text-xs font-medium text-slate-400">
                    {[property.suburb, property.zone, property.city].filter(Boolean).join(', ') || type}
                </p>
                <div className="mt-2 flex items-center justify-between">
                    <AvailabilityLine property={property} />
                    {isNewListing(property) && <span className="rounded-full bg-sky-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[.12em] text-sky-700">New</span>}
                </div>
            </div>
        </Link>
    );
}

function QuickViewPanel({ property, onClose, isFavourited, onToggleFavourite }) {
    const area = areaOf(property);
    const now = isAvailableNow(property);
    return (
        <div className="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label={`${property.title} quick view`}>
            <button type="button" aria-label="Close quick view" className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" onClick={onClose} />
            <div className="absolute inset-y-0 right-0 flex w-full max-w-md flex-col overflow-hidden bg-white shadow-2xl sm:rounded-l-3xl">
                <div className="relative h-56 shrink-0 bg-slate-100">
                    <PropertyMedia property={property} className="h-full w-full" />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-black/20" />
                    <div className="absolute left-4 top-4 flex gap-2">
                        {property.featured && <span className="rounded-full bg-amber-400 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-amber-950">Featured</span>}
                        {property.owner?.verified && <span className="rounded-full bg-emerald-400 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-[#062019]">Verified Owner</span>}
                        {property.verified && <span className="rounded-full bg-emerald-500 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white">Verified</span>}
                        <span className={cn('rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white', now ? 'bg-slate-950/70' : 'bg-amber-500/90')}>{availabilityOf(property)}</span>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close quick view"
                        className="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-slate-700 shadow-lg transition hover:bg-white"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto p-6">
                    <div className="flex items-baseline gap-2">
                        <span className="text-3xl font-black tracking-tight text-slate-950">{formatPrice(property.price)}</span>
                        <span className="text-sm font-bold text-slate-400">/month</span>
                    </div>
                    {property.deposit > 0 && <p className="mt-1 text-xs font-semibold text-slate-400">Deposit {formatPrice(property.deposit)}</p>}
                    <h3 className="mt-3 text-xl font-black tracking-tight text-slate-900">{property.title}</h3>
                    <p className="mt-1 flex items-start gap-1.5 text-sm text-slate-500">
                        <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" /> {locationOf(property)}
                    </p>

                    <div className="mt-5 grid grid-cols-2 gap-x-4 gap-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-3">
                        <div><p className="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Type</p><p className="mt-1 text-sm font-bold text-slate-900">{typeLabels[property.property_type] || property.property_type}</p></div>
                        <div><p className="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Bedrooms</p><p className="mt-1 text-sm font-bold text-slate-900">{property.bedrooms != null ? property.bedrooms : '—'}</p></div>
                        <div><p className="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Bathrooms</p><p className="mt-1 text-sm font-bold text-slate-900">{property.bathrooms} </p></div>
                        <div><p className="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Size</p><p className="mt-1 text-sm font-bold text-slate-900">{area || '—'}</p></div>
                        <div><p className="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Furnishing</p><p className="mt-1 text-sm font-bold text-slate-900">{property.furnished ? 'Furnished' : 'Unfurnished'}</p></div>
                        <div><p className="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Listed</p><p className="mt-1 text-sm font-bold text-slate-900">{timeAgo(property.created_at) || '—'}</p></div>
                    </div>

                    <div className="mt-5">
                        <p className="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                            <UserCheck className="h-4 w-4 text-emerald-600" /> Listed directly by the owner
                            {property.owner?.verified && <BadgeCheck className="h-4 w-4 text-emerald-600" />}
                        </p>
                        <p className="mt-1.5 text-xs leading-5 text-slate-400">
                            {property.verified
                                ? 'This listing has been verified by Dzimba. Never send money before viewing the property first.'
                                : 'Contact the owner directly — never send money or documents before viewing the property first.'}
                        </p>
                    </div>

                    {property.amenities?.length > 0 && (
                        <div className="mt-5">
                            <p className="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Amenities</p>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {property.amenities.slice(0, 8).map((amenity) => (
                                    <span key={amenity} className="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{amenity.replace(/_/g, ' ')}</span>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
                <div className="shrink-0 border-t border-slate-100 p-4">
                    <div className="flex items-center gap-2">
                        <Button type="button" variant="outline" className="flex-1" onClick={() => onToggleFavourite(property.id)}>
                            {isFavourited ? 'Saved' : 'Save property'}
                        </Button>
                        <Link href={route('property.show', property.id)} className="flex-1">
                            <Button className="w-full bg-slate-900 hover:bg-emerald-600">View full listing</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}

function SkeletonGrid() {
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="overflow-hidden rounded-[24px] border border-slate-200/80 bg-white">
                    <div className="h-56 animate-pulse bg-slate-200" />
                    <div className="space-y-3 p-6">
                        <div className="h-4 w-2/3 animate-pulse rounded-full bg-slate-200" />
                        <div className="h-3 w-1/2 animate-pulse rounded-full bg-slate-100" />
                        <div className="h-10 w-full animate-pulse rounded-full bg-slate-100" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function CompareBadgeValue({ value }) {
    return <p className="mt-1 text-sm font-bold text-slate-900">{value ?? '—'}</p>;
}

function ComparePanel({ items, onClose, onRemove }) {
    const rows = [
        ['Rent', (p) => `${formatPrice(p.price)}/mo`],
        ['Deposit', (p) => p.deposit > 0 ? formatPrice(p.deposit) : '—'],
        ['Type', (p) => typeLabels[p.property_type] || p.property_type],
        ['Bedrooms', (p) => p.bedrooms],
        ['Bathrooms', (p) => p.bathrooms],
        ['Size', (p) => areaOf(p)],
        ['Furnished', (p) => p.furnished ? 'Yes' : 'No'],
        ['Verified', (p) => p.verified ? 'Yes' : 'No'],
        ['Available', (p) => p.available_from && new Date(p.available_from).getTime() > Date.now() ? availabilityOf(p) : 'Now'],
    ];

    return (
        <div className="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label="Compare properties">
            <button type="button" aria-label="Close compare" className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" onClick={onClose} />
            <div className="absolute inset-x-0 bottom-0 flex max-h-[88vh] flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:inset-x-auto sm:inset-y-0 sm:right-0 sm:w-[880px] sm:max-w-[94vw] sm:rounded-l-3xl sm:rounded-tr-none">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 className="text-lg font-black tracking-tight text-slate-950">Compare {items.length} propert{items.length === 1 ? 'y' : 'ies'}</h3>
                        <p className="text-xs font-semibold text-slate-400">Side by side, no spreadsheet needed.</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Close compare" className="grid h-10 w-10 place-items-center rounded-full hover:bg-slate-100"><X className="h-5 w-5" /></button>
                </div>
                <div className="flex-1 overflow-auto px-6 py-5">
                    <div className="grid grid-cols-[8rem_repeat(3,minmax(0,1fr))] gap-4">
                        <div className="space-y-1.5">
                            {['Photo', 'Name', ...rows.map(([label]) => label), 'Action'].map((label, i) => <div key={i} className="h-9 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">{label}</div>)}
                        </div>
                        {items.map((property) => (
                            <div key={property.id} className="min-w-0">
                                <PropertyMedia property={property} className="h-24 rounded-xl" />
                                <p className="mt-2 truncate text-sm font-bold text-slate-900">{property.title}</p>
                                {rows.map(([label, get]) => <CompareBadgeValue key={label} value={get(property)} />)}
                                <button type="button" onClick={() => onRemove(property.id)} className="mt-1 text-xs font-black text-rose-500 hover:underline">Remove</button>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function MapResults({ properties, onSelect, center }) {
    const mountRef = useRef(null);
    const mapRef = useRef(null);

    useEffect(() => {
        if (!mountRef.current) return undefined;

        mapRef.current = L.map(mountRef.current, { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(mapRef.current);

        mapRef.current.attributionControl.setPrefix('');

        return () => {
            mapRef.current?.remove();
            mapRef.current = null;
        };
    }, []);

    useEffect(() => {
        const map = mapRef.current;
        if (!map) return;

        const markers = properties
            .filter((p) => p.latitude != null && p.longitude != null)
            .map((property) => L.marker([Number(property.latitude), Number(property.longitude)], {
                icon: L.divIcon({
                    className: '',
                    html: `<div class="dz-map-pin"><span>${formatPrice(property.price)}</span></div>`,
                    iconSize: [0, 0],
                    iconAnchor: [0, 0],
                }),
            }).addTo(map).bindPopup(
                `<a href="${route('property.show', property.id)}" class="dz-map-pop__link"><strong>${property.title}</strong><br/>${formatPrice(property.price)}/mo · ${[property.suburb, property.city].filter(Boolean).join(', ')}</a>`,
                { closeButton: false, className: 'dz-map-pop' }
            ));

        markers.forEach((marker, i) => {
            marker.on('click', () => {
                const property = properties.filter((p) => p.latitude != null && p.longitude != null)[i];
                onSelect(property);
            });
        });

        if (markers.length) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds(), { padding: [40, 40] });
        } else {
            map.setView([center.lat ?? -17.8292, center.lng ?? 31.0522], center.zoom ?? 11);
        }

        return () => markers.forEach((marker) => marker.remove());
    }, [properties]);

    return <div ref={mountRef} className="h-full w-full" aria-label="Property map" />;
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
                const response = await fetch(`${suggestionUrl}?q=${encodeURIComponent(term)}${suggestionUrl.includes('?') ? '' : ''}`, { signal: controller.current.signal });
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
        <div className="relative z-30 mx-auto mt-8 w-full max-w-3xl">
            <div className="flex items-center gap-2 rounded-[22px] border border-white/10 bg-white p-2 shadow-[0_30px_80px_-25px_rgba(0,0,0,.5)]">
                <div className="relative flex flex-1 items-center">
                    <Search className="pointer-events-none ml-4 h-5 w-5 shrink-0 text-slate-400" />
                    <input
                        value={text}
                        onChange={(e) => { setText(e.target.value); fetchSuggestions(e.target.value); }}
                        onFocus={() => setFocused(true)}
                        onBlur={() => setTimeout(() => setFocused(false), 160)}
                        onKeyDown={(e) => e.key === 'Enter' && submit(text, null)}
                        placeholder="Search suburb, city or property name — try “3 bed flat in Borrowdale under $700”"
                        className="h-12 w-full bg-transparent px-3 text-[15px] font-semibold text-slate-900 outline-none placeholder:font-medium placeholder:text-slate-400"
                        aria-label="Search properties"
                    />
                </div>
                <Button type="button" onClick={() => submit(text, null)} className="h-12 rounded-2xl bg-emerald-600 px-6 font-black hover:bg-emerald-500">
                    Search
                </Button>
            </div>

            {focused && suggestions.length > 0 && (
                <div className="mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
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
                            className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold text-slate-700 transition hover:bg-emerald-50"
                        >
                            <MapPin className="h-4 w-4 shrink-0 text-emerald-500" />
                            <span className="truncate">{suggestion.label}</span>
                            {suggestion.type === 'title' && suggestion.city && <span className="ml-auto shrink-0 text-xs text-slate-400">{suggestion.city}</span>}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function ExploreNeighbourhoods({ places, onPick }) {
    if (!places?.length) return null;
    return (
        <section className="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-600">Where to next?</span>
                    <h2 className="mt-2 text-2xl font-black tracking-[-.025em] text-slate-950 sm:text-3xl">Explore neighbourhoods</h2>
                </div>
            </div>
            <div className="mt-5 flex flex-wrap gap-3">
                {places.map((place) => (
                    <button
                        key={`${place.suburb}:${place.city}`}
                        type="button"
                        onClick={() => onPick(place)}
                        className="group inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-3.5 text-left transition hover:border-emerald-300 hover:bg-emerald-50"
                    >
                        <span className="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-emerald-700 transition group-hover:bg-emerald-200"><MapPin className="h-4 w-4" /></span>
                        <span>
                            <span className="block text-sm font-black text-slate-900">{place.suburb}</span>
                            <span className="block text-xs font-semibold text-slate-400">{place.city} · {place.total} listing{place.total === 1 ? '' : 's'}</span>
                        </span>
                    </button>
                ))}
            </div>
        </section>
    );
}

function ResultRail({ title, icon: Icon, items, emptyLabel }) {
    if (!items?.length) return null;
    return (
        <section className="mx-auto max-w-7xl px-4 pt-14 sm:px-6 lg:px-8">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-600"><Icon className="mr-1 inline h-3.5 w-3.5" />{title}</span>
                    <h2 className="mt-2 text-2xl font-black tracking-[-.025em] text-slate-950 sm:text-3xl">{title}</h2>
                </div>
                <span className="hidden text-xs font-bold text-slate-400 sm:block">{emptyLabel}</span>
            </div>
            <div className="mt-6 flex snap-x gap-4 overflow-x-auto pb-2">
                {items.map((property) => <JustListedCard key={property.id} property={property} />)}
            </div>
        </section>
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
    filters = {}, featured = [], justListed = [], favouriteIds = [], auth,
    marketplace = {}, explore = [], recommended = [], recentlyViewed = [],
}) {
    const [favs, setFavs] = useState(favouriteIds);
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
        availability: filters.availability || '',
        amenities: filters.amenities || [],
        sort: filters.sort || 'newest', page: pagination.current_page || 1,
    });
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [moreOpen, setMoreOpen] = useState(false);
    const [mobileNav, setMobileNav] = useState(false);
    const priceTimer = useRef();

    const compareEnabled = Boolean(marketplace.compareEnabled);
    const compareMax = Number(marketplace.compareMax || 3);

    useEffect(() => {
        router.on('start', () => setLoading(true));
        router.on('finish', () => setLoading(false));
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
        router.get(route('home'), buildParams(next, view), {
            replace: true, preserveState: true,
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
        setForm({ q: '', city: '', zone: '', suburb: '', property_type: '', bedrooms: '', bathrooms: '', min_price: '', max_price: '', furnished: '', verified: '', availability: '', amenities: [], sort: 'newest', page: 1 });
        router.get(route('home'), { view }, { replace: true, preserveState: true });
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
        window.scrollTo({ top: 650, behavior: 'smooth' });
    };

    const toggleFavourite = (propertyId) => {
        if (!auth?.user) return router.get(route('login'));
        setFavs((current) => current.includes(propertyId) ? current.filter((id) => id !== propertyId) : [...current, propertyId]);
        router.post(route('tenant.favourites.toggle', propertyId), {}, { preserveScroll: true });
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
        router.post(route('tenant.saved-searches.store'), {
            name: name || buildSaveName(form),
            criteria,
            notify: true,
        }, { preserveScroll: true });
    };

    const isFavourited = (id) => favs.includes(id);
    const popularThreshold = marketplace.popular;
    const activeFilters = ['city', 'zone', 'suburb', 'property_type', 'bedrooms', 'bathrooms', 'min_price', 'max_price', 'furnished', 'verified', 'availability', 'amenities', 'q']
        .filter((key) => Array.isArray(form[key])
            ? form[key].length > 0
            : form[key] !== '' && form[key] != null).length;
    const activeTier = form.max_price ? String(form.max_price) : '';
    const hero = featured?.length ? featured : properties.slice(0, 3);
    const heroProperty = hero[0];
    const pageTotal = pagination.last_page || 1;
    const pageNumbers = Array.from({ length: pageTotal }, (_, i) => i + 1);
    const hasResults = properties.length > 0;
    const isTenant = Boolean(auth?.user?.roles?.includes('Tenant'));
    const amenityOptions = Object.entries(marketplace.amenities || {});
    const mapEnabled = Boolean(marketplace.mapEnabled);

    const filterFields = [
        ['city', 'City', <><option value="">Any city</option>{cities.map((city) => <option key={city} value={city}>{city}</option>)}</>],
        ['zone', 'Area', <><option value="">Any area</option>{zones.map((zone) => <option key={zone} value={zone}>{zone}</option>)}</>],
        ['property_type', 'Property type', <><option value="">Any type</option>{Object.entries(typeLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</>],
        ['bedrooms', 'Bedrooms', <><option value="">Any</option><option value="0">Studio / 0</option>{bedroomOptions.filter((n) => n !== '0').map((n) => <option key={n} value={n}>{n}+ beds</option>)}</>],
        ['bathrooms', 'Bathrooms', <><option value="">Any</option>{bathroomOptions.map((n) => <option key={n} value={n}>{n}+ baths</option>)}</>],
    ];

    return (
        <>
            <Head title="Find a Home Without Agent Fees" />
            <div className="min-h-screen bg-[#f6f8f7] text-slate-900">
                <header className="sticky top-0 z-50 border-b border-white/10 bg-[#071713]/90 shadow-2xl shadow-slate-950/10 backdrop-blur-xl">
                    <div className="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <Link href={route('home')} aria-label="Dzimba home"><Brand dark /></Link>
                        <nav className="hidden items-center gap-8 md:flex">
                            <a href="#properties" className="text-sm font-semibold text-white/65 transition hover:text-white">Explore homes</a>
                            <a href="#how-it-works" className="text-sm font-semibold text-white/65 transition hover:text-white">How it works</a>
                        </nav>
                        <div className="hidden items-center gap-2 sm:flex">
                            {auth?.user ? (
                                <>
                                    <Link href={route(dashboardRouteFor(auth.user.roles))} className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'rounded-full px-4 text-white hover:bg-white/10 hover:text-white')}>My Dashboard</Link>
                                    <Link href={route(dashboardRouteFor(auth.user.roles))} className="group inline-flex h-10 items-center gap-2 rounded-full bg-emerald-400 px-5 text-sm font-extrabold text-[#062019] shadow-lg shadow-emerald-950/30 transition hover:bg-emerald-300">
                                        Manage properties <ArrowUpRight className="h-4 w-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <Link href={route('login')} className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'rounded-full px-4 text-white hover:bg-white/10 hover:text-white')}>Sign In</Link>
                                    <Link href={route('login')} className="group inline-flex h-10 items-center gap-2 rounded-full bg-emerald-400 px-5 text-sm font-extrabold text-[#062019] shadow-lg shadow-emerald-950/30 transition hover:bg-emerald-300">
                                        List your property <ArrowUpRight className="h-4 w-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                    </Link>
                                </>
                            )}
                        </div>
                        <button className="grid h-10 w-10 place-items-center rounded-full border border-white/10 text-white sm:hidden" onClick={() => setMobileNav((v) => !v)} aria-label="Toggle navigation">
                            {mobileNav ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                    {mobileNav && (
                        <div className="border-t border-white/10 px-4 pb-4 pt-3 sm:hidden">
                            <div className="grid gap-2">
                                <a href="#properties" onClick={() => setMobileNav(false)} className="rounded-xl px-4 py-3 text-sm font-semibold text-white/75 hover:bg-white/5">Explore homes</a>
                                <a href="#how-it-works" onClick={() => setMobileNav(false)} className="rounded-xl px-4 py-3 text-sm font-semibold text-white/75 hover:bg-white/5">How it works</a>
                                {auth?.user
                                    ? <Link href={route(dashboardRouteFor(auth.user.roles))} className="mt-1 rounded-xl bg-emerald-400 px-4 py-3 text-center text-sm font-extrabold text-[#062019]">My Dashboard</Link>
                                    : <Link href={route('login')} className="mt-1 rounded-xl bg-emerald-400 px-4 py-3 text-center text-sm font-extrabold text-[#062019]">List your property</Link>}
                            </div>
                        </div>
                    )}
                </header>

                <section className="relative overflow-hidden bg-[#071713]">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(52,211,153,.18),transparent_28%),radial-gradient(circle_at_10%_80%,rgba(20,184,166,.13),transparent_30%)]" />
                    <div className="pointer-events-none absolute -right-40 -top-40 h-[32rem] w-[32rem] rounded-full border border-emerald-300/10" />
                    <div className="pointer-events-none absolute right-[-10rem] top-[-10rem] h-[24rem] w-[24rem] rounded-full bg-emerald-400/10 blur-3xl" />
                    <div className="relative mx-auto max-w-7xl px-4 pb-28 pt-16 sm:px-6 sm:pt-20 lg:px-8 lg:pb-36 lg:pt-24">
                        <div className="max-w-4xl">
                            <span className="inline-flex items-center gap-2 rounded-full border border-emerald-300/15 bg-emerald-300/10 px-3.5 py-2 text-[10px] font-black uppercase tracking-[.18em] text-emerald-300"><Sparkles className="h-3.5 w-3.5" /> The smarter way to rent</span>
                            <h1 className="mt-7 max-w-4xl text-balance text-5xl font-black leading-[.96] tracking-[-.045em] text-white sm:text-6xl lg:text-7xl">
                                Find your next home.<br /><span className="bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300 bg-clip-text text-transparent">Deal directly with the owner.</span>
                            </h1>
                            <p className="mt-7 max-w-2xl text-balance text-base leading-7 text-white/60 sm:text-lg">Beautiful homes, verified owners and transparent renting — without the unnecessary agent commission.</p>
                            <HeroSearch initial={form.q} onSubmit={(q) => apply({ q, page: 1 })} suggestionUrl={marketplace.suggestionUrl} />
                            <div className="mt-6 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-white/70">
                                {['Verified listings', 'No agent commission', 'Direct owner contact'].map((item) => (
                                    <span key={item} className="inline-flex items-center gap-2"><CheckCircle2 className="h-4 w-4 text-emerald-300" />{item}</span>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="relative z-20 mx-auto -mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-[28px] border border-slate-200/80 bg-white p-3 shadow-[0_30px_80px_-35px_rgba(15,23,42,.35)] sm:p-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <button type="button" onClick={() => setFiltersOpen((v) => !v)} className="flex w-full items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold lg:hidden">
                                <span className="inline-flex items-center gap-2"><SlidersHorizontal className="h-4 w-4 text-emerald-600" /> Search & filters {activeFilters > 0 && <span className="grid h-5 min-w-5 place-items-center rounded-full bg-slate-900 px-1 text-[10px] text-white">{activeFilters}</span>}</span>
                                <ChevronDown className={cn('h-4 w-4 transition-transform', filtersOpen && 'rotate-180')} />
                            </button>
                            <div className="hidden items-center gap-2 lg:flex">
                                <span className="inline-flex items-center gap-2 px-1 text-sm font-bold text-slate-700"><SlidersHorizontal className="h-4 w-4 text-emerald-600" /> Search & filters {activeFilters > 0 && <span className="grid h-5 min-w-5 place-items-center rounded-full bg-slate-900 px-1 text-[10px] text-white">{activeFilters}</span>}</span>
                                <button type="button" onClick={() => setFiltersOpen((v) => !v)} aria-expanded={filtersOpen} className="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">
                                    {filtersOpen ? 'Hide' : 'Show'} filters
                                </button>
                            </div>
                            <label className="hidden w-full max-w-xs items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 md:flex">
                                <Search className="h-4 w-4 text-slate-400" />
                                <input
                                    value={form.q}
                                    onChange={(e) => setForm((f) => ({ ...f, q: e.target.value }))}
                                    onKeyDown={(e) => e.key === 'Enter' && apply({ q: e.target.value, page: 1 })}
                                    placeholder="Search by keyword…"
                                    className="w-full bg-transparent text-sm font-semibold text-slate-800 outline-none placeholder:text-slate-400"
                                    aria-label="Search by keyword"
                                />
                            </label>
                        </div>
                        <div className={cn('mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-5', !filtersOpen && 'hidden lg:grid')}>
                            {filterFields.map(([key, label, options]) => (
                                <label key={key} className="block">
                                    <span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">{label}</span>
                                    <select value={form[key]} onChange={liveSelect(key)} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10">{options}</select>
                                </label>
                            ))}
                            <label className="block">
                                <span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Furnishing</span>
                                <select value={form.furnished} onChange={liveSelect('furnished')} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10">
                                    <option value="">Any</option><option value="1">Furnished</option><option value="0">Unfurnished</option>
                                </select>
                            </label>
                            <label className="block">
                                <span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Min rent</span>
                                <input type="number" min="0" placeholder="$300" value={form.min_price} onChange={livePrice('min_price')} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" />
                            </label>
                            <label className="block">
                                <span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Max rent</span>
                                <input type="number" min="0" placeholder="$800" value={form.max_price} onChange={livePrice('max_price')} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" />
                            </label>
                            <div className="flex items-end">
                                <button type="button" onClick={resetFilters} className="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">Reset filters</button>
                            </div>
                        </div>

                        <div className="mt-3 border-t border-slate-100 pt-3">
                            <button type="button" onClick={() => setMoreOpen((v) => !v)} className="inline-flex h-10 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">
                                <SlidersHorizontal className="h-3.5 w-3.5 text-emerald-600" /> More filters
                                <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', moreOpen && 'rotate-180')} />
                            </button>
                            {moreOpen && (
                                <div className="mt-4 grid gap-5 md:grid-cols-3">
                                    <label className="block">
                                        <span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Verified listings</span>
                                        <select value={form.verified} onChange={liveSelect('verified')} className="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10">
                                            <option value="">Any</option><option value="1">Verified only</option><option value="0">Not verified</option>
                                        </select>
                                    </label>
                                    <label className="block">
                                        <span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Availability</span>
                                        <select value={form.availability} onChange={liveSelect('availability')} className="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10">
                                            <option value="">Any</option><option value="now">Available now</option><option value="upcoming">Available soon</option>
                                        </select>
                                    </label>
                                    <div className="flex items-end">
                                        <button type="button" onClick={() => setMoreOpen(false)} className="h-11 rounded-2xl bg-slate-900 px-5 text-sm font-black text-white transition hover:bg-emerald-600">Apply</button>
                                    </div>
                                    <div className="md:col-span-3">
                                        <span className="block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Amenities</span>
                                        {amenityOptions.length > 0 ? (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {amenityOptions.map(([key, label]) => (
                                                    <button
                                                        key={key}
                                                        type="button"
                                                        onClick={() => toggleAmenity(key)}
                                                        className={cn('rounded-full px-3.5 py-2 text-xs font-bold transition', form.amenities.includes(key) ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700')}
                                                    >
                                                        {label}
                                                    </button>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="mt-2 text-xs font-medium text-slate-400">No amenity filters configured yet.</p>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="sm:col-span-2 lg:col-span-5 mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                            <span className="mr-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Popular budgets</span>
                            {priceTiers.map(([value, label]) => (
                                <button key={value} type="button" onClick={() => apply({ max_price: activeTier === value ? '' : value, page: 1 })} className={cn('rounded-full px-3.5 py-2 text-xs font-bold transition', activeTier === value ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/10' : 'bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700')}>{label}</button>
                            ))}
                        </div>
                    </div>

                    <div className="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-2xl border border-emerald-200/70 bg-emerald-50/80 px-5 py-4">
                        <span className="inline-flex items-center gap-2 text-sm font-extrabold text-emerald-900"><ShieldCheck className="h-5 w-5 shrink-0 text-emerald-600" /> Direct from the owner — no traditional agent fees.</span>
                        <span className="text-xs font-semibold text-emerald-800/75">Never send money or personal documents before viewing the property and meeting the owner.</span>
                        <span className="text-xs font-bold text-emerald-800/90">✓ Verified listings carry a badge.</span>
                    </div>
                </section>

                {heroProperty && (
                    <section className="mx-auto max-w-7xl px-4 pt-14 sm:px-6 lg:px-8">
                        <div className="relative overflow-hidden rounded-[32px] bg-[#0a1714] shadow-[0_35px_80px_-35px_rgba(7,23,19,.55)]">
                            <div className="absolute inset-0 bg-[radial-gradient(circle_at_70%_40%,rgba(52,211,153,.12),transparent_35%)]" />
                            <div className="relative grid lg:grid-cols-[1.1fr_.9fr]">
                                <div className="relative min-h-[340px] overflow-hidden lg:min-h-[500px]">
                                    <Link href={route('property.show', heroProperty.id)} className="block h-full">
                                        <PropertyMedia property={heroProperty} className="h-full transition duration-700 hover:scale-[1.03]" />
                                    </Link>
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/10" />
                                    <div className="absolute left-5 top-5 flex gap-2">
                                        <span className="rounded-full border border-white/20 bg-black/40 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-white backdrop-blur">Spotlight home</span>
                                        {heroProperty.owner?.verified && <span className="rounded-full bg-emerald-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-white">Verified Owner</span>}
                                        {heroProperty.verified && <span className="rounded-full bg-emerald-400 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-[#062019]">Verified</span>}
                                    </div>
                                </div>
                                <div className="relative flex flex-col justify-center p-7 text-white sm:p-10 lg:p-14">
                                    <span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-300">Featured this week</span>
                                    <h2 className="mt-3 text-balance text-3xl font-black tracking-tight sm:text-4xl">{heroProperty.title}</h2>
                                    <p className="mt-3 flex items-center gap-2 text-sm text-white/55"><MapPin className="h-4 w-4 text-emerald-300" />{locationOf(heroProperty)}</p>
                                    <div className="mt-7"><span className="text-4xl font-black tracking-tight">{formatPrice(heroProperty.price)}</span><span className="ml-2 text-sm text-white/45">per month</span></div>
                                    <div className="mt-6 flex flex-wrap gap-2 text-xs font-bold text-white/75">
                                        {heroProperty.bedrooms != null && <span className="rounded-full border border-white/10 bg-white/5 px-3 py-2"><BedDouble className="mr-1.5 inline h-3.5 w-3.5 text-emerald-300" />{heroProperty.bedrooms} bed</span>}
                                        <span className="rounded-full border border-white/10 bg-white/5 px-3 py-2"><Bath className="mr-1.5 inline h-3.5 w-3.5 text-emerald-300" />{heroProperty.bathrooms} bath</span>
                                        <span className="rounded-full border border-white/10 bg-white/5 px-3 py-2"><Armchair className="mr-1.5 inline h-3.5 w-3.5 text-emerald-300" />{heroProperty.furnished ? 'Furnished' : 'Unfurnished'}</span>
                                    </div>
                                    <div className="mt-8 flex flex-wrap gap-3">
                                        <Link href={route('property.show', heroProperty.id)} className="group inline-flex h-12 items-center gap-2 rounded-full bg-emerald-400 px-6 text-sm font-black text-[#062019] shadow-lg shadow-emerald-950/30 transition hover:bg-emerald-300">View property <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" /></Link>
                                        <Link href={auth?.user ? route('property.show', heroProperty.id) : route('login')} className="inline-flex h-12 items-center rounded-full border border-white/15 bg-white/5 px-6 text-sm font-bold text-white transition hover:bg-white/10">Contact owner</Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                {marketplace.recommendations && recommended.length > 0 && (
                    <ResultRail title="Recommended for you" icon={Sparkles} items={recommended} emptyLabel="Based on how you browse" />
                )}
                {marketplace.recommendations && recentlyViewed.length > 0 && (
                    <ResultRail title="Recently viewed" icon={History} items={recentlyViewed} emptyLabel="Pick up where you left off" />
                )}

                {justListed.length > 0 && (
                    <section className="mx-auto max-w-7xl px-4 pt-14 sm:px-6 lg:px-8">
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-600">Fresh on the market</span>
                                <h2 className="mt-2 text-2xl font-black tracking-[-.025em] text-slate-950 sm:text-3xl">Just listed</h2>
                            </div>
                            <span className="hidden text-xs font-bold text-slate-400 sm:block">New homes appear here first</span>
                        </div>
                        <div className="mt-6 flex snap-x gap-4 overflow-x-auto pb-2">
                            {justListed.map((property) => <JustListedCard key={property.id} property={property} />)}
                        </div>
                    </section>
                )}

                <section id="properties" className="mx-auto max-w-7xl scroll-mt-24 px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                    <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-2xl font-black tracking-[-.025em] text-slate-950 sm:text-3xl">{total.toLocaleString()} propert{total === 1 ? 'y' : 'ies'} found</p>
                            <p className="mt-1.5 text-sm text-slate-500">
                                {activeFilters > 0 ? `Filtered by your search. ${activeFilters} filter${activeFilters === 1 ? '' : 's'} active.` : 'Every listing is rented directly to you by its owner.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {isTenant && (
                                <button type="button" onClick={saveSearch} className="inline-flex h-10 items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">
                                    <Save className="h-3.5 w-3.5" /> Save search
                                </button>
                            )}
                            <select value={form.sort} onChange={liveSelect('sort')} aria-label="Sort properties" className="h-10 rounded-full border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10">
                                <option value="newest">Newest first</option>
                                <option value="recently_updated">Recently updated</option>
                                <option value="price_asc">Price: low to high</option>
                                <option value="price_desc">Price: high to low</option>
                                <option value="price_per_m2">Best value per m²</option>
                                <option value="featured">Featured first</option>
                            </select>
                            <div className="flex items-center rounded-full border border-slate-200 bg-white p-1">
                                <button type="button" onClick={() => changeView('grid')} aria-label="Grid view" aria-pressed={view === 'grid'} className={cn('grid h-8 w-9 place-items-center rounded-full transition', view === 'grid' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-400 hover:text-slate-700')}><LayoutGrid className="h-4 w-4" /></button>
                                <button type="button" onClick={() => changeView('list')} aria-label="List view" aria-pressed={view === 'list'} className={cn('grid h-8 w-9 place-items-center rounded-full transition', view === 'list' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-400 hover:text-slate-700')}><List className="h-4 w-4" /></button>
                                {mapEnabled && (
                                    <button type="button" onClick={() => changeView('map')} aria-label="Map view" aria-pressed={view === 'map'} className={cn('grid h-8 w-9 place-items-center rounded-full transition', view === 'map' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-400 hover:text-slate-700')}><MapIcon className="h-4 w-4" /></button>
                                )}
                            </div>
                        </div>
                    </div>

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

                    {hasResults && view === 'map' && mapEnabled && (
                        <div className="h-[560px] overflow-hidden rounded-[28px] border border-slate-200 shadow-inner">
                            <MapResults properties={properties} onSelect={(property) => setQuickView(property)} center={marketplace.mapCenter} />
                        </div>
                    )}

                    {loading ? (
                        <SkeletonGrid />
                    ) : hasResults && view !== 'map' ? (
                        <>
                            {view === 'list' ? (
                                <div className="grid gap-5">
                                    {properties.map((property) => (
                                        <PropertyListRow
                                            key={property.id}
                                            property={property}
                                            threshold={popularThreshold}
                                            compareEnabled={compareEnabled}
                                            compareSelected={compareItems.some((p) => p.id === property.id)}
                                            onToggleCompare={toggleCompare}
                                            isFavourited={isFavourited(property.id)}
                                            onToggleFavourite={toggleFavourite}
                                            onQuickView={setQuickView}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {properties.map((property) => (
                                        <PropertyCard
                                            key={property.id}
                                            property={property}
                                            threshold={popularThreshold}
                                            compareEnabled={compareEnabled}
                                            compareSelected={compareItems.some((p) => p.id === property.id)}
                                            onToggleCompare={toggleCompare}
                                            isFavourited={isFavourited(property.id)}
                                            onToggleFavourite={toggleFavourite}
                                            onQuickView={setQuickView}
                                        />
                                    ))}
                                </div>
                            )}
                            {pageTotal > 1 && (
                                <nav className="mt-10 flex flex-wrap items-center justify-between gap-3">
                                    <p className="text-xs font-bold text-slate-400">Page {pagination.current_page} of {pageTotal}</p>
                                    <div className="flex flex-wrap gap-1.5">
                                        <Button type="button" variant="outline" size="sm" disabled={pagination.current_page <= 1} onClick={() => goToPage(pagination.current_page - 1)}>Previous</Button>
                                        {pageNumbers.map((num) => (
                                            <Button key={num} type="button" variant={num === pagination.current_page ? 'default' : 'outline'} size="sm" className={cn('rounded-xl', num === pagination.current_page && 'pointer-events-none')} onClick={() => goToPage(num)}>{num}</Button>
                                        ))}
                                        <Button type="button" variant="outline" size="sm" disabled={pagination.current_page >= pageTotal} onClick={() => goToPage(pagination.current_page + 1)}>Next</Button>
                                    </div>
                                </nav>
                            )}
                        </>
                    ) : null}

                    {hasResults && !loading && view !== 'map' && compareItems.length > 1 && (
                        <div className="fixed inset-x-0 bottom-0 z-[60] px-4 pb-4">
                            <div className="mx-auto flex max-w-2xl items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white/95 px-5 py-4 shadow-[0_30px_80px_-30px_rgba(15,23,42,.5)] backdrop-blur">
                                <div className="flex items-center gap-3">
                                    <span className="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-100 text-emerald-700"><ArrowLeftRight className="h-5 w-5" /></span>
                                    <div>
                                        <p className="text-sm font-black text-slate-900">Comparing {compareItems.length} propert{compareItems.length === 1 ? 'y' : 'ies'}</p>
                                        <p className="text-xs font-semibold text-slate-400">Tap “Compare” again to remove one</p>
                                    </div>
                                </div>
                                <Button type="button" onClick={() => setCompareOpen(true)} className="bg-slate-900 hover:bg-emerald-600">
                                    Compare now
                                </Button>
                            </div>
                        </div>
                    )}
                </section>

                <ExploreNeighbourhoods places={explore} onPick={(place) => apply({ suburb: place.suburb, city: place.city, page: 1 })} />

                <section id="how-it-works" className="border-y border-slate-200 bg-white px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                    <div className="mx-auto max-w-7xl">
                        <div className="max-w-2xl">
                            <span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-600">Simple by design</span>
                            <h2 className="mt-2 text-3xl font-black tracking-[-.025em] text-slate-950 sm:text-4xl">A better rental experience, from search to keys.</h2>
                            <p className="mt-3 text-sm leading-6 text-slate-500">Dzimba removes the unnecessary middle layer and gives owners and tenants a cleaner way to connect.</p>
                        </div>
                        <div className="mt-10 grid gap-5 md:grid-cols-3">
                            {[
                                { icon: Home, title: 'Owners list directly', copy: 'Create a polished listing, add your property details and reach people actively looking for a home.' },
                                { icon: Search, title: 'Tenants search freely', copy: 'Compare real listings, filter by what matters and connect directly with the property owner.' },
                                { icon: BadgeDollarSign, title: 'Keep costs simple', copy: 'Owners use an affordable subscription instead of giving away a large commission to an agent.' },
                            ].map(({ icon: Icon, title, copy }, i) => (
                                <div key={title} className="group rounded-[28px] border border-slate-200 bg-slate-50 p-7 transition duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-xl hover:shadow-slate-200/50">
                                    <div className="flex items-center justify-between">
                                        <span className="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700"><Icon className="h-5 w-5" /></span>
                                        <span className="text-4xl font-black text-slate-200">0{i + 1}</span>
                                    </div>
                                    <h3 className="mt-7 text-lg font-black tracking-tight text-slate-900">{title}</h3>
                                    <p className="mt-2 text-sm leading-6 text-slate-500">{copy}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <footer className="bg-[#071713] text-white">
                    <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <div className="flex flex-col justify-between gap-10 md:flex-row md:items-end">
                            <div>
                                <Link href={route('home')}><Brand dark /></Link>
                                <p className="mt-4 max-w-sm text-sm leading-6 text-white/45">A simpler way to rent — directly from owners, with less friction and more transparency.</p>
                            </div>
                            <div className="flex flex-wrap gap-x-7 gap-y-3 text-sm font-semibold text-white/55">
                                {auth?.user ? <Link href={route(dashboardRouteFor(auth.user.roles))} className="hover:text-emerald-300">My Dashboard</Link> : <Link href={route('login')} className="hover:text-emerald-300">Sign in</Link>}
                                <Link href={route('login')} className="hover:text-emerald-300">For owners</Link>
                                <Link href={route('login')} className="hover:text-emerald-300">For tenants</Link>
                            </div>
                        </div>
                        <div className="mt-10 border-t border-white/10 pt-6 text-xs font-medium text-white/30">© {new Date().getFullYear()} Dzimba. Rent directly. Live simply.</div>
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
                    onToggleFavourite={toggleFavourite}
                />
            )}
        </>
    );
}