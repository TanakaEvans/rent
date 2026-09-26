import { Head, Link, useForm } from '@inertiajs/react';
import { MessageSquareText, MapPin, Clock3, ArrowUpRight, Send } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { cn } from '@/lib/utils';

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';

function Message({ label, body, at, fromOwner }) {
    return (
        <div className={cn('rounded-xl px-3.5 py-2.5', fromOwner ? 'border-l-4 border-l-emerald-500 bg-emerald-50/60' : 'border border-border bg-muted/40')}>
            <p className={cn('text-[10px] font-extrabold uppercase tracking-wider', fromOwner ? 'text-emerald-700' : 'text-muted-foreground')}>
                {label}{at && <span className="ml-2 font-medium normal-case tracking-normal text-muted-foreground">{formatDate(at)}</span>}
            </p>
            <p className="mt-0.5 whitespace-pre-line text-sm leading-relaxed text-foreground">{body}</p>
        </div>
    );
}

function ReplyForm({ enquiry }) {
    const { data, setData, post, processing, errors, reset } = useForm({ reply: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('tenant.enquiries.reply', enquiry.id), {
            preserveScroll: true,
            onSuccess: () => reset('reply'),
        });
    };

    return (
        <form onSubmit={submit} className="mt-3">
            <label htmlFor={`reply-${enquiry.id}`} className="sr-only">Reply to the owner</label>
            <div className="flex items-end gap-2">
                <textarea
                    id={`reply-${enquiry.id}`}
                    rows={2}
                    maxLength={1000}
                    value={data.reply}
                    onChange={(e) => setData('reply', e.target.value)}
                    placeholder="Reply to the owner…"
                    className="field h-auto min-h-11 flex-1 resize-y py-2.5"
                />
                <button
                    type="submit"
                    disabled={processing || data.reply.trim() === ''}
                    className="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                >
                    <Send className="h-4 w-4" /> {processing ? 'Sending…' : 'Send'}
                </button>
            </div>
            {errors.reply && <p className="mt-1.5 text-xs font-medium text-destructive">{errors.reply}</p>}
        </form>
    );
}

export default function TenantEnquiries({ enquiries = [] }) {
    return (
        <MainLayout title="My Enquiries">
            <Head title="My Enquiries" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">My Enquiries</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Conversations with owners — <span className="font-semibold text-foreground">{enquiries.length}</span> {enquiries.length === 1 ? 'thread' : 'threads'}.
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
                    {enquiries.map((enquiry) => {
                        const messages = enquiry.messages ?? [];
                        return (
                            <article key={enquiry.id} className="surface flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:p-5">
                                <div className="min-w-0 flex-1">
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
                                        <Message label="You asked" body={enquiry.message} at={enquiry.created_at} />
                                        {messages.length === 0 && enquiry.reply && (
                                            <Message label="Owner replied" body={enquiry.reply} at={enquiry.replied_at} fromOwner />
                                        )}
                                        {messages.map((message) => (
                                            <Message
                                                key={message.id}
                                                label={message.sender_role === 'owner' ? 'Owner replied' : 'You replied'}
                                                body={message.body}
                                                at={message.created_at}
                                                fromOwner={message.sender_role === 'owner'}
                                            />
                                        ))}
                                    </div>

                                    {enquiry.status === 'closed' ? (
                                        <p className="mt-3 text-xs font-medium text-muted-foreground">The owner closed this conversation.</p>
                                    ) : (
                                        <ReplyForm enquiry={enquiry} />
                                    )}

                                    <p className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-medium text-muted-foreground">
                                        <span className="flex items-center gap-1"><Clock3 className="h-3 w-3" /> Sent {formatDate(enquiry.created_at)}</span>
                                    </p>
                                </div>

                                <Link
                                    href={route('property.show', enquiry.property_id)}
                                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-primary/30 px-3.5 py-2 text-xs font-bold text-primary transition-colors hover:bg-primary hover:text-white"
                                >
                                    View listing <ArrowUpRight className="h-3.5 w-3.5" />
                                </Link>
                            </article>
                        );
                    })}
                </div>
            )}
        </MainLayout>
    );
}
