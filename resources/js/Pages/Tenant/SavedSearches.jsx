import { Head, Link, router } from '@inertiajs/react';
import { Search, Save, Bell, BellOff, Trash2, Pencil, Tag } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
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

const availabilityLabels = { now: 'Available now', upcoming: 'Available soon' };

const chipFor = (key, value) => {
    if (Array.isArray(value)) {
        return value.length ? value.map((item) => item.replace(/_/g, ' ')) : null;
    }
    if (value === '' || value == null) return null;
    switch (key) {
        case 'property_type':
            return typeLabels[value] || value;
        case 'bedrooms':
            return `${value}+ bed`;
        case 'bathrooms':
            return `${value}+ bath`;
        case 'min_price':
            return `From $${value}`;
        case 'max_price':
            return `Under $${value}`;
        case 'furnished':
            return value === '1' || value === 1 ? 'Furnished' : 'Unfurnished';
        case 'verified':
            return 'Verified only';
        case 'availability':
            return availabilityLabels[value] || value;
        case 'q':
            return `“${value}”`;
        case 'sort':
            return null;
        default:
            return String(value);
    }
};

const runSearch = (criteria) => {
    const params = {};
    Object.entries(criteria || {}).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            if (value.length) params[key] = value;
        } else if (value !== '' && value != null) {
            params[key] = value;
        }
    });
    router.get(route('home'), params);
};

export default function TenantSavedSearches({ searches = [] }) {
    const rename = (search) => {
        const name = window.prompt('Name this saved search', search.name);
        if (name === null || !name.trim()) return;
        router.put(route('tenant.saved-searches.update', search.id), { name: name.trim() }, { preserveScroll: true });
    };

    const toggleNotify = (search) => {
        router.put(route('tenant.saved-searches.update', search.id), { notify: !search.notify }, { preserveScroll: true });
    };

    const remove = (search) => {
        if (!window.confirm(`Delete saved search “${search.name}”?`)) return;
        router.delete(route('tenant.saved-searches.destroy', search.id), { preserveScroll: true });
    };

    return (
        <MainLayout title="Saved Searches">
            <Head title="Saved Searches" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Saved Searches</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Your saved marketplace filters — run them again any time and get alerted when new homes match.
                    </p>
                </div>
                <Link href={route('home')} className={cn(buttonVariants())}>
                    <Search className="h-4 w-4" /> New Search
                </Link>
            </div>

            {searches.length === 0 ? (
                <EmptyState
                    icon={Save}
                    title="No saved searches yet"
                    description="On the marketplace, filter to your taste and press “Save search” to keep those filters one tap away."
                    action={<Link href={route('home')} className={cn(buttonVariants())}><Search className="h-4 w-4" /> Browse the Marketplace</Link>}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {searches.map((search) => (
                        <article key={search.id} className="surface group flex flex-col p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_40px_-16px_rgba(16,60,45,0.28)]">
                            <div className="flex items-start justify-between gap-3">
                                <h3 className="flex items-center gap-2 text-base font-extrabold tracking-tight text-foreground">
                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                                        <Search className="h-4.5 w-4.5" />
                                    </span>
                                    <span className="truncate">{search.name}</span>
                                </h3>
                                <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-extrabold text-emerald-700 ring-1 ring-emerald-200">
                                    {search.match_count} match{search.match_count === 1 ? '' : 'es'}
                                </span>
                            </div>

                            <div className="mt-4 flex flex-wrap gap-1.5">
                                {Object.entries(search.criteria || {}).flatMap(([key, value]) => {
                                    const chips = chipFor(key, value);
                                    if (!chips) return [];
                                    return (Array.isArray(chips) ? chips : [chips]).map((text) => (
                                        <span key={`${key}:${text}`} className="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-1 text-[11px] font-semibold capitalize text-muted-foreground">
                                            <Tag className="h-3 w-3 text-primary" /> {text}
                                        </span>
                                    ));
                                })}
                            </div>

                            <div className="mt-5 flex items-center gap-2 border-t border-border pt-4">
                                <Button size="sm" className="flex-1" onClick={() => runSearch(search.criteria)}>
                                    <Search className="h-4 w-4" /> Run search
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => toggleNotify(search)}
                                    aria-pressed={search.notify}
                                    title={search.notify ? 'Notifications on — click to stop' : 'Notifications off — click to enable'}
                                >
                                    {search.notify ? <Bell className="h-4 w-4 text-emerald-600" /> : <BellOff className="h-4 w-4" />}
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => rename(search)} aria-label={`Rename ${search.name}`}>
                                    <Pencil className="h-4 w-4" />
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => remove(search)} aria-label={`Delete ${search.name}`} className="text-muted-foreground hover:bg-rose-50 hover:text-rose-500">
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}