import { Head, Link, router } from '@inertiajs/react';
import { Users, MapPin, Clock3, Phone, BadgeCheck, Send } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import ActionErrors from '@/Components/Shared/ActionErrors';
import { cn } from '@/lib/utils';

const STATUS_OPTIONS = [
    { value: '', label: 'All statuses' },
    { value: 'interested', label: 'Interested' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'archived', label: 'Archived' },
];

const formatRelative = (value) => {
    if (!value) return '';
    const diff = Date.now() - new Date(value).getTime();
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

export default function OwnerInterestsIndex({ queue = [], counts = {}, filters = {}, properties = [] }) {
    const total = queue.reduce((sum, property) => sum + property.interests.length, 0);

    const changeFilter = (key, value) => {
        router.get(route('owner.interests.index'), { ...filters, [key]: value || '' }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const contact = (interest) =>
        router.post(route('owner.interests.contact', interest.id), {}, { preserveScroll: true });
    const reopen = (interest) =>
        router.post(route('owner.interests.reopen', interest.id), {}, { preserveScroll: true });
    const archive = (interest) =>
        router.post(route('owner.interests.archive', interest.id), {}, { preserveScroll: true });

    return (
        <MainLayout title="Express Interests">
            <Head title="Express Interests" />

            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Express Interests</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Tenants who tapped interest on your listings — <span className="font-semibold text-foreground">{total}</span> shown.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {STATUS_OPTIONS.map(({ value, label }) => {
                        const active = (filters.status || '') === value;
                        return (
                            <button
                                key={value || 'all'}
                                type="button"
                                aria-pressed={active}
                                onClick={() => changeFilter('status', active ? '' : value)}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-xs font-bold transition-colors',
                                    active
                                        ? 'border-primary bg-primary text-white'
                                        : 'border-border bg-background text-muted-foreground hover:border-primary/50'
                                )}
                            >
                                {label}{value ? ` (${counts[value] ?? 0})` : ''}
                            </button>
                        );
                    })}
                    <select
                        value={filters.property ?? ''}
                        onChange={(e) => changeFilter('property', e.target.value)}
                        className="field w-auto rounded-full py-1 text-xs font-semibold"
                    >
                        <option value="">All properties</option>
                        {properties.map((property) => (
                            <option key={property.id} value={property.id}>
                                {property.title}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <ActionErrors className="mb-5" />

            {queue.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title="No interests yet"
                    description="When tenants tap Express Interest on your listings, their leads will appear here for you to contact."
                />
            ) : (
                <div className="space-y-5">
                    {queue.map((property) => (
                        <section key={property.id} className="surface overflow-hidden">
                            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/30 px-5 py-3.5">
                                <div className="flex items-center gap-3">
                                    <Link href={route('owner.properties.show', property.id)} className="h-11 w-14 shrink-0 overflow-hidden rounded-lg">
                                        <PropertyArt property={property} />
                                    </Link>
                                    <div className="min-w-0">
                                        <Link href={route('owner.properties.show', property.id)} className="truncate font-bold text-foreground hover:text-primary">
                                            {property.title}
                                        </Link>
                                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                            {property.city || 'Location on request'}
                                        </p>
                                    </div>
                                </div>
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                                    <Users className="h-3.5 w-3.5" /> {property.interests.length} {property.interests.length === 1 ? 'lead' : 'leads'}
                                </span>
                            </header>

                            <ul className="divide-y divide-border">
                                {property.interests.map((interest) => (
                                    <li key={interest.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full brand-gradient text-xs font-extrabold text-white">
                                                    {(interest.tenant?.name || '?').charAt(0).toUpperCase()}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="flex items-center gap-1.5 text-sm font-bold text-foreground">
                                                        {interest.tenant?.name || 'Tenant'}
                                                        {interest.tenant?.badge_tier && interest.tenant.badge_tier !== 'none' && (
                                                            <BadgeCheck className="h-4 w-4 text-emerald-500" aria-label="Verified tenant" />
                                                        )}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {[interest.tenant?.city, interest.tenant?.employment_status]
                                                            .filter(Boolean)
                                                            .join(' · ') || 'Tenant profile on request'}
                                                    </p>
                                                </div>
                                            </div>
                                            {interest.note && (
                                                <p className="mt-2 max-w-xl text-xs italic text-muted-foreground">“{interest.note}”</p>
                                            )}
                                            <p className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-medium text-muted-foreground">
                                                <span className="inline-flex items-center gap-1">
                                                    <Clock3 className="h-3 w-3" /> {formatRelative(interest.expressed_at)}
                                                </span>
                                                {interest.tenant?.phone && (
                                                    <span className="inline-flex items-center gap-1">
                                                        <Phone className="h-3 w-3" /> {interest.tenant.phone}
                                                    </span>
                                                )}
                                                {interest.tenant?.email && (
                                                    <span className="inline-flex items-center gap-1">
                                                        <Send className="h-3 w-3" /> {interest.tenant.email}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={interest.status} />
                                            {interest.status === 'interested' && (
                                                <button
                                                    type="button"
                                                    onClick={() => contact(interest)}
                                                    className={cn('inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-colors', 'bg-emerald-500 text-white hover:bg-emerald-400')}
                                                >
                                                    <Send className="h-3.5 w-3.5" /> Contacted
                                                </button>
                                            )}
                                            {interest.status === 'contacted' && (
                                                <button
                                                    type="button"
                                                    onClick={() => reopen(interest)}
                                                    className="rounded-lg border border-border px-3 py-1.5 text-xs font-bold text-muted-foreground transition-colors hover:border-primary/50 hover:text-foreground"
                                                >
                                                    Re-open
                                                </button>
                                            )}
                                            {interest.status !== 'archived' && (
                                                <button
                                                    type="button"
                                                    onClick={() => archive(interest)}
                                                    className="rounded-lg px-3 py-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:text-foreground"
                                                >
                                                    Archive
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}