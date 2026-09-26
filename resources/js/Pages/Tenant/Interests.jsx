import { Head, Link, router } from '@inertiajs/react';
import { Users, MapPin, Clock3, ArrowUpRight, Undo2 } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { formatPrice, paymentPeriod } from '@/lib/listing';

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';

export default function TenantInterests({ interests = [] }) {
    const withdraw = (interest) =>
        router.post(route('tenant.interests.withdraw', interest.id), {}, { preserveScroll: true });

    return (
        <MainLayout title="My Interests">
            <Head title="My Interests" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Interests</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Properties you have expressed interest in — <span className="font-semibold text-foreground">{interests.length}</span>{' '}
                    {interests.length === 1 ? 'listing' : 'listings'}.
                </p>
            </div>

            {interests.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title="No interests yet"
                    description="Tap Express Interest on any available listing and the owner will be notified instantly — no message needed."
                />
            ) : (
                <div className="space-y-4">
                    {interests.map((interest) => (
                        <article key={interest.id} className="surface flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:p-5">
                            <div className="flex-1">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <h3 className="font-bold leading-snug text-foreground">{interest.property?.title}</h3>
                                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                                            {interest.property?.city || 'Location on request'}
                                        </p>
                                        <p className="mt-1 text-sm font-bold text-primary">
                                            {formatPrice(interest.property?.price, interest.property?.currency)} <span className="text-xs font-semibold text-muted-foreground">{paymentPeriod(interest.property?.payment_terms)}</span>
                                        </p>
                                    </div>
                                    <StatusBadge status={interest.status} />
                                </div>

                                {interest.note && (
                                    <p className="mt-3 rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm italic text-muted-foreground">“{interest.note}”</p>
                                )}

                                <p className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-medium text-muted-foreground">
                                    <span className="flex items-center gap-1"><Clock3 className="h-3 w-3" /> Expressed {formatDate(interest.created_at)}</span>
                                    {interest.status === 'interested' && <span>Awaiting the owner’s response.</span>}
                                    {interest.status === 'contacted' && <span>The owner has been in touch.</span>}
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                {interest.status !== 'archived' && (
                                    <button
                                        type="button"
                                        onClick={() => withdraw(interest)}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-semibold text-muted-foreground transition-colors hover:border-rose-300 hover:text-rose-600"
                                    >
                                        <Undo2 className="h-3.5 w-3.5" /> Withdraw
                                    </button>
                                )}
                                {interest.property?.status === 'available' && (
                                    <Link
                                        href={route('property.show', interest.property_id)}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 px-3.5 py-2 text-xs font-bold text-primary transition-colors hover:bg-primary hover:text-white"
                                    >
                                        View listing <ArrowUpRight className="h-3.5 w-3.5" />
                                    </Link>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}