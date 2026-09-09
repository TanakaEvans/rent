import { Head, Link } from '@inertiajs/react';
import { MessageSquareText, MapPin, Clock3, ArrowUpRight } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { cn } from '@/lib/utils';

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';

export default function TenantEnquiries({ enquiries = [] }) {
    return (
        <MainLayout title="My Enquiries">
            <Head title="My Enquiries" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Enquiries</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Questions you have asked owners — <span className="font-semibold text-foreground">{enquiries.length}</span> {enquiries.length === 1 ? 'thread' : 'threads'}.
                </p>
            </div>

            {enquiries.length === 0 ? (
                <EmptyState
                    icon={MessageSquareText}
                    title="No enquiries yet"
                    description="Ask an owner anything before you commit — availability, bills, parking. It is free."
                />
            ) : (
                <div className="space-y-4">
                    {enquiries.map((enquiry) => (
                        <article key={enquiry.id} className="surface flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:p-5">
                            <div className="flex-1">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <h3 className="font-bold leading-snug text-foreground">{enquiry.property?.title}</h3>
                                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                            {[enquiry.property?.suburb, enquiry.property?.city].filter(Boolean).join(', ') || 'Location on request'}
                                        </p>
                                    </div>
                                    <StatusBadge status={enquiry.status} />
                                </div>

                                <div className="mt-3 space-y-2">
                                    <div className={cn('rounded-xl border border-border bg-muted/40 px-3.5 py-2.5')}>
                                        <p className="text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground">You asked</p>
                                        <p className="mt-0.5 text-sm leading-relaxed text-foreground">{enquiry.message}</p>
                                    </div>
                                    {enquiry.reply && (
                                        <div className="rounded-xl border-l-4 border-l-emerald-500 bg-emerald-50/60 px-3.5 py-2.5">
                                            <p className="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Owner replied</p>
                                            <p className="mt-0.5 text-sm leading-relaxed text-foreground">{enquiry.reply}</p>
                                        </div>
                                    )}
                                </div>

                                <p className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-medium text-muted-foreground">
                                    <span className="flex items-center gap-1"><Clock3 className="h-3 w-3" /> Sent {formatDate(enquiry.created_at)}</span>
                                    {enquiry.replied_at && (
                                        <span className="flex items-center gap-1"><Clock3 className="h-3 w-3" /> Replied {formatDate(enquiry.replied_at)}</span>
                                    )}
                                </p>
                            </div>

                            {enquiry.property?.status === 'available' && (
                                <Link
                                    href={route('property.show', enquiry.property_id)}
                                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-primary/30 px-3.5 py-2 text-xs font-bold text-primary transition-colors hover:bg-primary hover:text-white"
                                >
                                    View listing <ArrowUpRight className="h-3.5 w-3.5" />
                                </Link>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}