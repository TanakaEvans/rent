import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
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
    Send,
    ClipboardCheck,
    Heart,
} from 'lucide-react';
import Brand from '@/Components/Shared/Brand';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import { dashboardRouteFor } from '@/lib/roles';

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

export default function MarketplaceShow({ property, isFavourited = false, viewingSlots = [], applicationState = null, auth }) {
    const images = property.images?.length ? property.images : [];
    const [active, setActive] = useState(0);
    const [saved, setSaved] = useState(isFavourited);
    const flash = usePage().props.flash || {};
    const enquiry = useForm({ message: '', phone: '' });
    const viewing = useForm({ slot_id: '', request_message: '' });
    const application = useForm({ message: '' });

    const isTenant = auth?.user?.roles?.some((role) => role.name === 'Tenant');

    const toggleFavourite = () => {
        if (!auth?.user) {
            router.get(route('login'));
            return;
        }
        setSaved((current) => !current);
        router.post(route('tenant.favourites.toggle', property.id), {}, { preserveScroll: true });
    };

    const sendEnquiry = (e) => {
        e.preventDefault();
        enquiry.post(route('tenant.enquiries.store', property.id), {
            preserveScroll: true,
            onSuccess: () => {
                enquiry.reset('message', 'phone');
            },
        });
    };

    const sendViewing = (e) => {
        e.preventDefault();
        viewing.post(route('tenant.viewings.store'), {
            preserveScroll: true,
            onSuccess: () => {
                viewing.reset('slot_id', 'request_message');
            },
        });
    };

    const sendApplication = (e) => {
        e.preventDefault();
        application.post(route('tenant.applications.store', property.id), {
            preserveScroll: true,
            onSuccess: () => {
                application.reset('message');
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

    return (
        <>
            <Head title={`${property.title} | Dzimba`} />

            <div className="min-h-screen app-bg">
                <header className="dark-sidebar sticky top-0 z-40 border-b border-white/5">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <Link href={route('home')} aria-label="Dzimba home">
                            <Brand dark />
                        </Link>
                        <div className="flex items-center gap-2">
                            {auth?.user ? (
                                <>
                                    <Link
                                        href={route(dashboardRouteFor(auth.user.roles))}
                                        className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'text-white hover:bg-white/10 hover:text-white')}
                                    >
                                        My Dashboard
                                    </Link>
                                    <Link href={route(dashboardRouteFor(auth.user.roles))} className={cn(buttonVariants({ size: 'sm' }), 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/40 hover:bg-emerald-400')}>
                                        Manage Properties
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                </>
                            ) : (
                                <>
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
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <Link href={route('home')} className="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80">
                        <ArrowLeft className="h-4 w-4" /> Back to search results
                    </Link>

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
                                    {property.featured && (
                                        <span className="absolute left-4 top-4 inline-flex items-center gap-1 rounded-full bg-amber-400 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider text-amber-950 shadow-lg">
                                            <Star className="h-3.5 w-3.5 fill-current" /> Featured
                                        </span>
                                    )}
                                    {property.verified && (
                                        <span className="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-emerald-500/90 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider text-white shadow-lg backdrop-blur-sm">
                                            <BadgeCheck className="h-3.5 w-3.5" /> Verified property
                                        </span>
                                    )}
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
                            <div className="surface p-6">
                                <h1 className="text-balance text-2xl font-extrabold tracking-tight text-foreground">{property.title}</h1>
                                {location && (
                                    <p className="mt-1.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                                        <MapPin className="h-4 w-4 shrink-0 text-emerald-500" /> {location}
                                    </p>
                                )}

                                <div className="mt-5 flex flex-wrap items-baseline gap-2">
                                    <span className="text-3xl font-extrabold tracking-tight text-foreground">{formatPrice(property.price)}</span>
                                    <span className="text-sm font-medium text-muted-foreground">/ month</span>
                                    {property.deposit > 0 && <span className="ml-1 text-xs font-semibold text-muted-foreground">Deposit {formatPrice(property.deposit)}</span>}
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
                                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"><CalendarClock className="h-4 w-4 text-primary" /> Available</div>
                                        <div className="mt-1 text-lg font-extrabold text-foreground">
                                            {property.available_from ? new Date(property.available_from).toLocaleDateString() : 'Now'}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Owner info */}
                            <div className="surface p-6">
                                <h2 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Listed by Owner</h2>
                                <div className="mt-4 flex items-center gap-3">
                                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full brand-gradient text-sm font-extrabold text-white">
                                        {(property.owner?.name || '?').charAt(0).toUpperCase()}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate font-bold text-foreground">{property.owner?.name || 'Dzimba owner'}</p>
                                        <p className="flex items-center gap-1 text-xs font-semibold text-emerald-600">
                                            <BadgeCheck className="h-3.5 w-3.5" /> Direct owner — no agent
                                        </p>
                                    </div>
                                </div>
                                {property.owner?.email && (
                                    <p className="mt-4 flex items-center gap-2 rounded-xl border border-border bg-muted/50 px-3.5 py-2.5 text-sm font-semibold text-muted-foreground">
                                        <Mail className="h-4 w-4 text-primary" /> {property.owner.email}
                                    </p>
                                )}
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Listed for type <span className="font-bold capitalize text-foreground">{typeLabels[property.property_type] || property.property_type}</span>
                                    {property.address && <> at <span className="font-bold text-foreground">{property.address}</span></>}.
                                </p>

                                <div className="mt-5 flex flex-col gap-2">
                                    {flash.success && (
                                        <p className="rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm font-semibold text-emerald-800">
                                            {flash.success}
                                        </p>
                                    )}

                                    {isTenant ? (
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
                                            <button
                                                type="submit"
                                                disabled={enquiry.processing}
                                                className={cn(buttonVariants(), 'w-full')}
                                            >
                                                <Send className="h-4 w-4" /> {enquiry.processing ? 'Sending…' : 'Send Enquiry'}
                                            </button>
                                            <p className="pt-1 text-center text-[11px] font-medium text-muted-foreground">
                                                Free for tenants — the owner replies right here, no agent.
                                            </p>
                                        </form>
                                    ) : auth?.user ? (
                                        <p className="rounded-xl border border-border bg-muted/50 px-3.5 py-2.5 text-xs font-semibold text-muted-foreground">
                                            Owners respond to enquiries from their inbox.
                                        </p>
                                    ) : (
                                        <Link href={route('login')} className={cn(buttonVariants(), 'w-full')}>
                                            <Send className="h-4 w-4" /> Enquire with Owner
                                        </Link>
                                    )}

                                    {isTenant && viewingSlots.length > 0 && (
                                        <form onSubmit={sendViewing} className="flex flex-col gap-2 rounded-2xl border border-dashed border-primary/25 bg-primary/[0.03] p-3.5">
                                            <span className="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-primary">
                                                <CalendarClock className="h-4 w-4" /> Book a Viewing
                                            </span>
                                            <select
                                                value={viewing.data.slot_id}
                                                onChange={(e) => viewing.setData('slot_id', e.target.value)}
                                                className="field w-full"
                                            >
                                                <option value="">Pick an open slot…</option>
                                                {viewingSlots.map((slot) => (
                                                    <option key={slot.id} value={slot.id}>
                                                        {formatSlot(slot.starts_at)} – {formatSlot(slot.ends_at)}
                                                    </option>
                                                ))}
                                            </select>
                                            <input
                                                type="text"
                                                value={viewing.data.request_message}
                                                onChange={(e) => viewing.setData('request_message', e.target.value)}
                                                placeholder="Anything about the visit (optional)"
                                                maxLength={1000}
                                                className="field w-full"
                                            />
                                            {viewing.errors.slot_id && (
                                                <p className="text-xs font-semibold text-rose-600">{viewing.errors.slot_id}</p>
                                            )}
                                            {viewing.errors.request_message && (
                                                <p className="text-xs font-semibold text-rose-600">{viewing.errors.request_message}</p>
                                            )}
                                            <button
                                                type="submit"
                                                disabled={viewing.processing}
                                                className={cn(buttonVariants({ size: 'sm' }), 'w-full')}
                                            >
                                                <CalendarClock className="h-4 w-4" /> {viewing.processing ? 'Booking…' : 'Request Viewing'}
                                            </button>
                                        </form>
                                    )}

                                    {isTenant ? (
                                        applicationState ? (
                                            <div className="flex items-center gap-2 rounded-2xl border border-primary/25 bg-primary/[0.03] p-3.5">
                                                <StatusBadge status={applicationState.status} />
                                                <p className="text-xs font-semibold text-muted-foreground">
                                                    {applicationState.status === 'approved'
                                                        ? 'Approved — the owner will reach out to start the lease.'
                                                        : applicationState.status === 'rejected'
                                                          ? 'Your application was not approved for this property.'
                                                          : 'Application submitted — the owner will review it shortly.'}
                                                </p>
                                            </div>
                                        ) : (
                                            <form onSubmit={sendApplication} className="flex flex-col gap-2 rounded-2xl border border-dashed border-primary/25 bg-primary/[0.03] p-3.5">
                                                <span className="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-primary">
                                                    <ClipboardCheck className="h-4 w-4" /> Apply to Rent
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
                                        )
                                    ) : (
                                        <Link href={route('login')} className={cn(buttonVariants({ variant: 'outline' }), 'w-full border-primary/30 text-primary hover:bg-primary hover:text-white')}>
                                            <ClipboardCheck className="h-4 w-4" /> Apply to Rent
                                        </Link>
                                    )}
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
                                </div>
                            </div>
                        </aside>
                    </div>
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
        </>
    );
}