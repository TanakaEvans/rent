import { useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Search, MapPin, BedDouble, Bath, Armchair, BadgeCheck, Star,
    ShieldCheck, Landmark, BadgeDollarSign, ArrowRight, Warehouse,
    Home, Building2, ChevronDown, SlidersHorizontal, Heart, Sparkles,
    CheckCircle2, Menu, X, ArrowUpRight,
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

export default function MarketplaceIndex({
    properties = [], total = 0, pagination = {}, cities = [], zones = [],
    filters = {}, featured = [], favouriteIds = [], auth,
}) {
    const [favs, setFavs] = useState(favouriteIds);
    const [form, setForm] = useState({
        city: filters.city || '', zone: filters.zone || '', property_type: filters.property_type || '',
        bedrooms: filters.bedrooms != null ? String(filters.bedrooms) : '',
        bathrooms: filters.bathrooms != null ? String(filters.bathrooms) : '',
        min_price: filters.min_price != null ? String(filters.min_price) : '',
        max_price: filters.max_price != null ? String(filters.max_price) : '',
        furnished: filters.furnished != null ? (filters.furnished ? '1' : '0') : '',
        sort: filters.sort || 'newest', page: pagination.current_page || 1,
    });
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [mobileNav, setMobileNav] = useState(false);
    const priceTimer = useRef();

    const apply = (merge) => {
        const next = { ...form, ...merge };
        setForm(next);
        const { page, ...rest } = next;
        const params = Object.fromEntries(Object.entries(rest).filter(([, v]) => v !== '' && v !== null && v !== undefined));
        if (page > 1) params.page = page;
        router.get(route('home'), params, {
            replace: true, preserveState: true,
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
        setForm({ city: '', zone: '', property_type: '', bedrooms: '', bathrooms: '', min_price: '', max_price: '', furnished: '', sort: 'newest', page: 1 });
        router.get(route('home'), {}, { replace: true, preserveState: true });
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

    const isFavourited = (id) => favs.includes(id);
    const activeFilters = ['city', 'zone', 'property_type', 'bedrooms', 'bathrooms', 'min_price', 'max_price', 'furnished'].filter((key) => form[key] !== '' && form[key] != null).length;
    const activeTier = form.max_price ? String(form.max_price) : '';
    const hero = featured?.length ? featured : properties.slice(0, 3);
    const pageTotal = pagination.last_page || 1;
    const pageNumbers = Array.from({ length: pageTotal }, (_, i) => i + 1);
    const heroProperty = hero[0];

    const PropertyBadges = ({ property }) => (
        <div className="absolute inset-x-0 top-0 flex items-start justify-between p-4">
            <div className="flex flex-wrap gap-2">
                {property.featured && <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-slate-950/75 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white shadow-xl backdrop-blur-md"><Star className="h-3 w-3 fill-amber-300 text-amber-300" /> Featured</span>}
                {property.verified && <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/20 bg-emerald-500/90 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-white shadow-xl backdrop-blur-md"><BadgeCheck className="h-3 w-3" /> Verified</span>}
            </div>
            <button type="button" onClick={() => toggleFavourite(property.id)} aria-pressed={isFavourited(property.id)} aria-label={isFavourited(property.id) ? 'Remove from favourites' : 'Save to favourites'} className="grid h-10 w-10 place-items-center rounded-full border border-white/20 bg-black/35 text-white backdrop-blur-md transition hover:scale-105 hover:bg-white hover:text-rose-500">
                <Heart className={cn('h-4 w-4', isFavourited(property.id) && 'fill-current text-rose-500')} />
            </button>
        </div>
    );

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
                    {mobileNav && <div className="border-t border-white/10 px-4 pb-4 pt-3 sm:hidden"><div className="grid gap-2"><a href="#properties" onClick={() => setMobileNav(false)} className="rounded-xl px-4 py-3 text-sm font-semibold text-white/75 hover:bg-white/5">Explore homes</a><a href="#how-it-works" onClick={() => setMobileNav(false)} className="rounded-xl px-4 py-3 text-sm font-semibold text-white/75 hover:bg-white/5">How it works</a>{auth?.user ? <Link href={route(dashboardRouteFor(auth.user.roles))} className="mt-1 rounded-xl bg-emerald-400 px-4 py-3 text-center text-sm font-extrabold text-[#062019]">My Dashboard</Link> : <Link href={route('login')} className="mt-1 rounded-xl bg-emerald-400 px-4 py-3 text-center text-sm font-extrabold text-[#062019]">List your property</Link>}</div></div>}
                </header>

                <section className="relative overflow-hidden bg-[#071713]">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(52,211,153,.18),transparent_28%),radial-gradient(circle_at_10%_80%,rgba(20,184,166,.13),transparent_30%)]" />
                    <div className="pointer-events-none absolute -right-40 -top-40 h-[32rem] w-[32rem] rounded-full border border-emerald-300/10" />
                    <div className="pointer-events-none absolute right-[-10rem] top-[-10rem] h-[24rem] w-[24rem] rounded-full bg-emerald-400/10 blur-3xl" />
                    <div className="relative mx-auto max-w-7xl px-4 pb-28 pt-16 sm:px-6 sm:pt-20 lg:px-8 lg:pb-36 lg:pt-28">
                        <div className="max-w-4xl">
                            <span className="inline-flex items-center gap-2 rounded-full border border-emerald-300/15 bg-emerald-300/10 px-3.5 py-2 text-[10px] font-black uppercase tracking-[.18em] text-emerald-300"><Sparkles className="h-3.5 w-3.5" /> The smarter way to rent</span>
                            <h1 className="mt-7 max-w-4xl text-balance text-5xl font-black leading-[.96] tracking-[-.045em] text-white sm:text-6xl lg:text-7xl">
                                Find your next home.<br /><span className="bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300 bg-clip-text text-transparent">Deal directly with the owner.</span>
                            </h1>
                            <p className="mt-7 max-w-2xl text-balance text-base leading-7 text-white/60 sm:text-lg">Beautiful homes, verified owners and transparent renting — without the unnecessary agent commission.</p>
                            <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-white/70">
                                {['Verified listings', 'No agent commission', 'Direct owner contact'].map((item) => <span key={item} className="inline-flex items-center gap-2"><CheckCircle2 className="h-4 w-4 text-emerald-300" />{item}</span>)}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="relative z-20 mx-auto -mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-[28px] border border-slate-200/80 bg-white p-3 shadow-[0_30px_80px_-35px_rgba(15,23,42,.35)] sm:p-4">
                        <button type="button" onClick={() => setFiltersOpen((v) => !v)} className="mb-2 flex w-full items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold lg:hidden"><span className="inline-flex items-center gap-2"><SlidersHorizontal className="h-4 w-4 text-emerald-600" /> Search & filters {activeFilters > 0 && <span className="grid h-5 min-w-5 place-items-center rounded-full bg-slate-900 px-1 text-[10px] text-white">{activeFilters}</span>}</span><ChevronDown className={cn('h-4 w-4 transition-transform', filtersOpen && 'rotate-180')} /></button>
                        <div className={cn('grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-5', !filtersOpen && 'hidden lg:grid')}>
                            {[['city','City',<><option value="">Any city</option>{cities.map((city)=><option key={city} value={city}>{city}</option>)}</>],['zone','Area',<><option value="">Any area</option>{zones.map((zone)=><option key={zone} value={zone}>{zone}</option>)}</>],['property_type','Property type',<><option value="">Any type</option>{Object.entries(typeLabels).map(([key,label])=><option key={key} value={key}>{label}</option>)}</>],['bedrooms','Bedrooms',<><option value="">Any</option><option value="0">Studio / 0</option>{bedroomOptions.filter(n=>n!=='0').map(n=><option key={n} value={n}>{n}+ beds</option>)}</>],['bathrooms','Bathrooms',<><option value="">Any</option>{bathroomOptions.map(n=><option key={n} value={n}>{n}+ baths</option>)}</>]].map(([key,label,options])=><label key={key} className="block"><span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">{label}</span><select value={form[key]} onChange={liveSelect(key)} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10">{options}</select></label>)}
                            <label className="block"><span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Furnishing</span><select value={form.furnished} onChange={liveSelect('furnished')} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10"><option value="">Any</option><option value="1">Furnished</option><option value="0">Unfurnished</option></select></label>
                            <label className="block"><span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Min rent</span><input type="number" min="0" placeholder="$300" value={form.min_price} onChange={livePrice('min_price')} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" /></label>
                            <label className="block"><span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Max rent</span><input type="number" min="0" placeholder="$800" value={form.max_price} onChange={livePrice('max_price')} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" /></label>
                            <label className="block"><span className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Sort by</span><select value={form.sort} onChange={liveSelect('sort')} className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10"><option value="newest">Newest first</option><option value="price_asc">Price: low to high</option><option value="price_desc">Price: high to low</option></select></label>
                            <div className="flex items-end"><button type="button" onClick={resetFilters} className="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">Reset filters</button></div>
                            <div className="sm:col-span-2 lg:col-span-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3"><span className="mr-1 text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Popular budgets</span>{priceTiers.map(([value,label])=><button key={value} type="button" onClick={()=>apply({max_price:activeTier===value?'':value,page:1})} className={cn('rounded-full px-3.5 py-2 text-xs font-bold transition',activeTier===value?'bg-slate-900 text-white shadow-lg shadow-slate-900/10':'bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700')}>{label}</button>)}</div>
                        </div>
                    </div>
                </section>

                {heroProperty && <section className="mx-auto max-w-7xl px-4 pt-14 sm:px-6 lg:px-8">
                    <div className="relative overflow-hidden rounded-[32px] bg-[#0a1714] shadow-[0_35px_80px_-35px_rgba(7,23,19,.55)]">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_70%_40%,rgba(52,211,153,.12),transparent_35%)]" />
                        <div className="relative grid lg:grid-cols-[1.1fr_.9fr]">
                            <div className="relative min-h-[340px] overflow-hidden lg:min-h-[500px]"><PropertyArt property={heroProperty} className="h-full transition duration-700 hover:scale-[1.03]" /><div className="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/10" /><div className="absolute left-5 top-5 flex gap-2"><span className="rounded-full border border-white/20 bg-black/40 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-white backdrop-blur">Spotlight home</span>{heroProperty.verified&&<span className="rounded-full bg-emerald-400 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-[#062019]">Verified</span>}</div></div>
                            <div className="relative flex flex-col justify-center p-7 text-white sm:p-10 lg:p-14"><span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-300">Featured this week</span><h2 className="mt-3 text-balance text-3xl font-black tracking-tight sm:text-4xl">{heroProperty.title}</h2><p className="mt-3 flex items-center gap-2 text-sm text-white/55"><MapPin className="h-4 w-4 text-emerald-300" />{[heroProperty.suburb,heroProperty.zone,heroProperty.city].filter(Boolean).join(', ')}</p><div className="mt-7"><span className="text-4xl font-black tracking-tight">{formatPrice(heroProperty.price)}</span><span className="ml-2 text-sm text-white/45">per month</span></div><div className="mt-6 flex flex-wrap gap-2 text-xs font-bold text-white/75">{heroProperty.bedrooms!=null&&<span className="rounded-full border border-white/10 bg-white/5 px-3 py-2"><BedDouble className="mr-1.5 inline h-3.5 w-3.5 text-emerald-300" />{heroProperty.bedrooms} bed</span>}<span className="rounded-full border border-white/10 bg-white/5 px-3 py-2"><Bath className="mr-1.5 inline h-3.5 w-3.5 text-emerald-300" />{heroProperty.bathrooms} bath</span><span className="rounded-full border border-white/10 bg-white/5 px-3 py-2"><Armchair className="mr-1.5 inline h-3.5 w-3.5 text-emerald-300" />{heroProperty.furnished?'Furnished':'Unfurnished'}</span></div><div className="mt-8 flex flex-wrap gap-3"><Link href={route('property.show',heroProperty.id)} className="group inline-flex h-12 items-center gap-2 rounded-full bg-emerald-400 px-6 text-sm font-black text-[#062019] shadow-lg shadow-emerald-950/30 transition hover:bg-emerald-300">View property <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" /></Link><Link href={auth?.user ? route('property.show',heroProperty.id) : route('login')} className="inline-flex h-12 items-center rounded-full border border-white/15 bg-white/5 px-6 text-sm font-bold text-white transition hover:bg-white/10">Contact owner</Link></div></div>
                        </div>
                    </div>
                </section>}

                <section id="properties" className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                    <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-600">Curated for you</span><h2 className="mt-2 text-3xl font-black tracking-[-.025em] text-slate-950 sm:text-4xl">Available homes</h2><p className="mt-2 text-sm text-slate-500">{total} listing{total!==1?'s':''} available right now.</p></div><div className="hidden rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-500 shadow-sm sm:block"><span className="mr-2 inline-block h-2 w-2 rounded-full bg-emerald-400" />Live listings</div></div>
                    {properties.length===0?<EmptyState icon={Search} title="No properties match your filters" description="Try adjusting your search criteria, or check back soon — new homes are listed every week." action={<Button onClick={resetFilters}>View all properties</Button>} />:<div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">{properties.map((property)=>{const TypeIcon=typeIcons[property.property_type]||Home;return <article key={property.id} className="group overflow-hidden rounded-[28px] border border-slate-200/80 bg-white shadow-[0_12px_35px_-22px_rgba(15,23,42,.35)] transition duration-300 hover:-translate-y-1.5 hover:border-slate-300 hover:shadow-[0_30px_70px_-30px_rgba(15,23,42,.35)]"><div className="relative h-60 overflow-hidden bg-slate-100"><Link href={route('property.show',property.id)} className="block h-full"><PropertyArt property={property} className="transition duration-700 group-hover:scale-105" /></Link><PropertyBadges property={property} /></div><div className="p-5 sm:p-6"><div className="flex items-start justify-between gap-4"><div className="min-w-0"><h3 className="truncate text-lg font-black tracking-tight text-slate-900"><Link href={route('property.show',property.id)} className="transition hover:text-emerald-700">{property.title}</Link></h3><p className="mt-1.5 flex items-center gap-1.5 truncate text-sm text-slate-500"><MapPin className="h-3.5 w-3.5 shrink-0 text-emerald-500" />{[property.suburb,property.zone,property.city].filter(Boolean).join(', ')||'Location on request'}</p></div></div><div className="mt-5 flex flex-wrap gap-1.5">{<span className="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-600"><TypeIcon className="h-3.5 w-3.5 text-emerald-600" />{typeLabels[property.property_type]||property.property_type}</span>}{property.bedrooms>0&&<span className="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-600"><BedDouble className="h-3.5 w-3.5 text-slate-400" />{property.bedrooms}</span>}<span className="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-600"><Bath className="h-3.5 w-3.5 text-slate-400" />{property.bathrooms}</span><span className="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-600"><Armchair className="h-3.5 w-3.5 text-slate-400" />{property.furnished?'Furnished':'Unfurnished'}</span></div><div className="mt-6 flex items-end justify-between gap-3 border-t border-slate-100 pt-5"><div><div className="text-2xl font-black tracking-tight text-slate-950">{formatPrice(property.price)}<span className="ml-1 text-xs font-bold text-slate-400">/mo</span></div>{property.deposit>0&&<div className="mt-1 text-[11px] font-medium text-slate-400">Deposit {formatPrice(property.deposit)}</div>}</div><Link href={route('property.show',property.id)} className="inline-flex h-10 items-center gap-1.5 rounded-full bg-slate-900 px-4 text-xs font-black text-white transition hover:bg-emerald-600">View <ArrowRight className="h-3.5 w-3.5" /></Link></div></div></article>})}</div>}
                    {pageTotal>1&&<nav className="mt-10 flex flex-wrap items-center justify-between gap-3"><p className="text-xs font-bold text-slate-400">Page {pagination.current_page} of {pageTotal}</p><div className="flex flex-wrap gap-1.5"><Button type="button" variant="outline" size="sm" disabled={pagination.current_page<=1} onClick={()=>goToPage(pagination.current_page-1)}>Previous</Button>{pageNumbers.map(num=><Button key={num} type="button" variant={num===pagination.current_page?'default':'outline'} size="sm" className={cn('rounded-xl',num===pagination.current_page&&'pointer-events-none')} onClick={()=>goToPage(num)}>{num}</Button>)}<Button type="button" variant="outline" size="sm" disabled={pagination.current_page>=pageTotal} onClick={()=>goToPage(pagination.current_page+1)}>Next</Button></div></nav>}
                </section>

                <section id="how-it-works" className="border-y border-slate-200 bg-white px-4 py-16 sm:px-6 lg:px-8 lg:py-20"><div className="mx-auto max-w-7xl"><div className="max-w-2xl"><span className="text-[10px] font-black uppercase tracking-[.2em] text-emerald-600">Simple by design</span><h2 className="mt-2 text-3xl font-black tracking-[-.025em] text-slate-950 sm:text-4xl">A better rental experience, from search to keys.</h2><p className="mt-3 text-sm leading-6 text-slate-500">Dzimba removes the unnecessary middle layer and gives owners and tenants a cleaner way to connect.</p></div><div className="mt-10 grid gap-5 md:grid-cols-3">{[{icon:Home,title:'Owners list directly',copy:'Create a polished listing, add your property details and reach people actively looking for a home.'},{icon:Search,title:'Tenants search freely',copy:'Compare real listings, filter by what matters and connect directly with the property owner.'},{icon:BadgeDollarSign,title:'Keep costs simple',copy:'Owners use an affordable subscription instead of giving away a large commission to an agent.'}].map(({icon:Icon,title,copy},i)=><div key={title} className="group rounded-[28px] border border-slate-200 bg-slate-50 p-7 transition duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-xl hover:shadow-slate-200/50"><div className="flex items-center justify-between"><span className="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700"><Icon className="h-5 w-5" /></span><span className="text-4xl font-black text-slate-200">0{i+1}</span></div><h3 className="mt-7 text-lg font-black tracking-tight text-slate-900">{title}</h3><p className="mt-2 text-sm leading-6 text-slate-500">{copy}</p></div>)}</div></div></section>

                <footer className="bg-[#071713] text-white"><div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8"><div className="flex flex-col justify-between gap-10 md:flex-row md:items-end"><div><Link href={route('home')}><Brand dark /></Link><p className="mt-4 max-w-sm text-sm leading-6 text-white/45">A simpler way to rent — directly from owners, with less friction and more transparency.</p></div><div className="flex flex-wrap gap-x-7 gap-y-3 text-sm font-semibold text-white/55">{auth?.user ? <Link href={route(dashboardRouteFor(auth.user.roles))} className="hover:text-emerald-300">My Dashboard</Link> : <Link href={route('login')} className="hover:text-emerald-300">Sign in</Link>}<Link href={route('login')} className="hover:text-emerald-300">For owners</Link><Link href={route('login')} className="hover:text-emerald-300">For tenants</Link></div></div><div className="mt-10 border-t border-white/10 pt-6 text-xs font-medium text-white/30">© {new Date().getFullYear()} Dzimba. Rent directly. Live simply.</div></div></footer>
            </div>
        </>
    );
}
