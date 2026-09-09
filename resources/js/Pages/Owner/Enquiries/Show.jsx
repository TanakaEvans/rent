import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Phone, MapPin, Mail, User, Send, Lock, Clock3 } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';

const formatDate = (value) =>
    value ? new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';

export default function OwnerEnquiriesShow({ enquiry }) {
    const [reply, setReply] = useState('');
    const closed = enquiry.status === 'closed';

    const sendReply = () => {
        router.post(route('owner.enquiries.reply', enquiry.id), { reply }, { preserveScroll: true });
    };

    const closeThread = () => {
        router.post(route('owner.enquiries.close', enquiry.id), {}, { preserveScroll: true });
    };

    return (
        <MainLayout title="Enquiry Thread">
            <Head title="Enquiry Thread" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Enquiry from tenant</p>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">{enquiry.tenant?.name || 'Tenant'}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        about <span className="font-semibold text-foreground">{enquiry.property?.title}</span>
                    </p>
                </div>
                <StatusBadge status={enquiry.status} />
            </div>

            <div className="grid gap-5 lg:grid-cols-[1.6fr_1fr]">
                <div className="space-y-4">
                    {/* Tenant message */}
                    <article className="surface p-5 sm:p-6">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <p className="flex items-center gap-2 text-sm font-bold text-foreground">
                                <span className="grid h-9 w-9 place-items-center rounded-full brand-gradient text-sm font-extrabold text-white">
                                    {(enquiry.tenant?.name || '?').charAt(0).toUpperCase()}
                                </span>
                                {enquiry.tenant?.name || 'Tenant'}
                            </p>
                            <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                <Clock3 className="h-3.5 w-3.5" /> {formatDate(enquiry.created_at)}
                            </span>
                        </div>
                        <p className="whitespace-pre-wrap text-[15px] leading-relaxed text-foreground">{enquiry.message}</p>
                    </article>

                    {/* Owner reply */}
                    {enquiry.reply ? (
                        <article className="surface border-l-4 border-l-emerald-500 p-5 sm:p-6">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <p className="flex items-center gap-2 text-sm font-bold text-foreground">
                                    <span className="grid h-9 w-9 place-items-center rounded-full bg-emerald-500 text-sm font-extrabold text-white">
                                        You
                                    </span>
                                    Your reply
                                </p>
                                <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    <Clock3 className="h-3.5 w-3.5" /> {formatDate(enquiry.replied_at)}
                                </span>
                            </div>
                            <p className="whitespace-pre-wrap text-[15px] leading-relaxed text-foreground">{enquiry.reply}</p>
                        </article>
                    ) : null}

                    {/* Reply box */}
                    {closed ? (
                        <p className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                            This enquiry thread is closed.
                        </p>
                    ) : (
                        <section className="surface p-5 sm:p-6">
                            <h3 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Reply to tenant</h3>
                            <textarea
                                value={reply}
                                onChange={(e) => setReply(e.target.value)}
                                placeholder="Write your reply…"
                                rows={4}
                                maxLength={1000}
                                className="field mt-3 w-full resize-none"
                            />
                            <p className="mt-1 text-right text-[11px] text-muted-foreground">{reply.length}/1000</p>
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    onClick={sendReply}
                                    disabled={!reply.trim()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <Send className="h-4 w-4" /> Send Reply
                                </button>
                                <button
                                    type="button"
                                    onClick={closeThread}
                                    className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2.5 text-sm font-semibold text-muted-foreground transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                >
                                    <Lock className="h-4 w-4" /> Close Thread
                                </button>
                            </div>
                        </section>
                    )}
                </div>

                {/* Property + tenant context */}
                <aside className="space-y-4">
                    <div className="surface p-5">
                        <h3 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Property</h3>
                        <p className="mt-2 font-bold leading-snug text-foreground">{enquiry.property?.title}</p>
                        <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                            {[enquiry.property?.suburb, enquiry.property?.city].filter(Boolean).join(', ') || 'Location on request'}
                        </p>
                        <div className="mt-3 flex items-center gap-2">
                            <StatusBadge status={enquiry.property?.status} />
                            <span className="text-sm font-bold text-foreground">
                                ${Number(enquiry.property?.price || 0).toLocaleString()}
                                <span className="font-semibold text-muted-foreground">/mo</span>
                            </span>
                        </div>
                        <a
                            href={route('owner.properties.show', enquiry.property_id)}
                            className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80"
                        >
                            <ArrowLeft className="h-3.5 w-3.5 rotate-90" /> Manage property
                        </a>
                    </div>

                    <div className="surface p-5">
                        <h3 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Tenant</h3>
                        <p className="mt-2 flex items-center gap-2 text-sm font-bold text-foreground">
                            <User className="h-4 w-4 text-primary" /> {enquiry.tenant?.name || 'Tenant'}
                        </p>
                        {enquiry.tenant?.email && (
                            <p className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                <Mail className="h-3.5 w-3.5 shrink-0" /> {enquiry.tenant.email}
                            </p>
                        )}
                        {enquiry.phone && (
                            <p className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                <Phone className="h-3.5 w-3.5 shrink-0" /> {enquiry.phone}
                            </p>
                        )}
                        {enquiry.read_at && (
                            <p className={cn('mt-3 rounded-lg bg-muted/60 px-3 py-2 text-[11px] font-medium text-muted-foreground')}>
                                Read {formatDate(enquiry.read_at)}
                            </p>
                        )}
                    </div>
                </aside>
            </div>
        </MainLayout>
    );
}