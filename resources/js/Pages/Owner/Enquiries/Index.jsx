import { Head, Link } from '@inertiajs/react';
import { MessageSquareText, MapPin, ArrowRight, Clock3 } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyArt from '@/Components/Shared/PropertyArt';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { cn } from '@/lib/utils';

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

export default function OwnerEnquiriesIndex({ properties = [] }) {
    const total = properties.reduce((sum, property) => sum + property.enquiries.length, 0);

    return (
        <MainLayout title="Enquiry Inbox">
            <Head title="Enquiry Inbox" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Enquiry Inbox</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Tenant enquiries grouped by property — <span className="font-semibold text-foreground">{total}</span> total.
                    </p>
                </div>
            </div>

            {properties.length === 0 ? (
                <EmptyState
                    icon={MessageSquareText}
                    title="No enquiries yet"
                    description="When tenants enquire about your listings, their questions will appear here."
                />
            ) : (
                <div className="space-y-5">
                    {properties.map((property) => (
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
                                            {[property.suburb, property.city].filter(Boolean).join(', ') || 'Location on request'}
                                        </p>
                                    </div>
                                </div>
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">
                                    <MessageSquareText className="h-3.5 w-3.5" /> {property.enquiries.length} {property.enquiries.length === 1 ? 'enquiry' : 'enquiries'}
                                </span>
                            </header>

                            <ul className="divide-y divide-border">
                                {property.enquiries.map((enquiry) => (
                                    <li key={enquiry.id}>
                                        <Link
                                            href={route('owner.enquiries.show', enquiry.id)}
                                            className={cn(
                                                'flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-muted/40',
                                                enquiry.status === 'new' && 'bg-sky-50/60'
                                            )}
                                        >
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full brand-gradient text-xs font-extrabold text-white">
                                                        {(enquiry.tenant?.name || '?').charAt(0).toUpperCase()}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-bold text-foreground">
                                                            {enquiry.tenant?.name || 'Tenant'}
                                                            {enquiry.status === 'new' && <span className="ml-2 rounded-full bg-sky-500 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-white">New</span>}
                                                        </p>
                                                        <p className="truncate text-xs text-muted-foreground">{enquiry.message}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="hidden items-center gap-1 text-[11px] font-medium text-muted-foreground sm:inline-flex">
                                                    <Clock3 className="h-3 w-3" /> {formatRelative(enquiry.created_at)}
                                                </span>
                                                <StatusBadge status={enquiry.status} />
                                                <ArrowRight className="h-4 w-4 text-muted-foreground" />
                                            </div>
                                        </Link>
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