import { useEffect, useRef } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MessageSquareText, LifeBuoy, Send, MapPin, ArrowLeft, Home } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import EmptyState from '@/Components/Shared/EmptyState';
import Avatar from '@/Components/Shared/Avatar';
import { cn } from '@/lib/utils';

const formatTime = (value) =>
    value ? new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';

/**
 * The name and monogram to show for a conversation from the current user's
 * point of view: the other party on a direct thread, ZimRent Support on a
 * support thread.
 */
function peerOf(conversation, currentUserId) {
    if (conversation.type === 'support') {
        return { name: 'ZimRent Support', initials: 'ZR' };
    }
    const other = (conversation.participants || []).find((p) => p.id !== currentUserId);
    return { name: other?.name || 'ZimRent user', initials: null, user: other };
}

function Thread({ conversation, currentUserId }) {
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });
    const endRef = useRef(null);
    const peer = peerOf(conversation, currentUserId);

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [conversation.messages?.length, conversation.id]);

    const submit = (e) => {
        e.preventDefault();
        post(route('chat.message', conversation.id), {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center gap-3 border-b border-border px-4 py-3">
                <Link href={route('chat.index')} className="grid h-9 w-9 place-items-center rounded-lg text-muted-foreground hover:bg-muted lg:hidden" aria-label="Back to conversations">
                    <ArrowLeft className="h-4.5 w-4.5" />
                </Link>
                {conversation.type === 'support' ? (
                    <span className="grid h-10 w-10 place-items-center rounded-full bg-primary/10 text-primary"><LifeBuoy className="h-5 w-5" /></span>
                ) : (
                    <Avatar user={peer.user} name={peer.name} size={40} />
                )}
                <div className="min-w-0 flex-1">
                    <h3 className="truncate font-bold leading-tight text-foreground">{peer.name}</h3>
                    {conversation.property ? (
                        <Link href={route('property.show', conversation.property.id)} className="flex items-center gap-1 truncate text-xs text-muted-foreground hover:text-primary">
                            <MapPin className="h-3 w-3 shrink-0 text-emerald-500" />
                            {conversation.property.title}
                        </Link>
                    ) : (
                        <p className="truncate text-xs text-muted-foreground">{conversation.subject || 'Support conversation'}</p>
                    )}
                </div>
            </div>

            <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                {(conversation.messages || []).length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">No messages yet — say hello.</p>
                ) : (
                    (conversation.messages || []).map((message) => {
                        const mine = message.sender_id === currentUserId;
                        return (
                            <div key={message.id} className={cn('flex flex-col', mine ? 'items-end' : 'items-start')}>
                                <div className={cn('max-w-[80%] rounded-2xl px-3.5 py-2 text-sm leading-relaxed', mine ? 'bg-primary text-primary-foreground' : 'border border-border bg-muted/50 text-foreground')}>
                                    {!mine && <span className="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-muted-foreground">{message.sender?.name || 'Former user'}</span>}
                                    <span className="whitespace-pre-wrap break-words">{message.body}</span>
                                </div>
                                <span className="mt-0.5 px-1 text-[10px] text-muted-foreground">{formatTime(message.created_at)}</span>
                            </div>
                        );
                    })
                )}
                <div ref={endRef} />
            </div>

            <form onSubmit={submit} className="border-t border-border px-4 py-3">
                <div className="flex items-end gap-2">
                    <textarea
                        rows={1}
                        maxLength={2000}
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                if (data.body.trim() !== '') submit(e);
                            }
                        }}
                        placeholder="Write a message…"
                        className="field h-auto min-h-11 flex-1 resize-y py-2.5"
                    />
                    <button
                        type="submit"
                        disabled={processing || data.body.trim() === ''}
                        className="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                    >
                        <Send className="h-4 w-4" /> {processing ? 'Sending…' : 'Send'}
                    </button>
                </div>
                {errors.body && <p className="mt-1.5 text-xs font-medium text-destructive">{errors.body}</p>}
            </form>
        </div>
    );
}

export default function ChatIndex({ conversations = [], activeConversation = null }) {
    const currentUserId = usePage().props.auth?.user?.id;

    // Light polling: while a thread is open and the tab is focused, refetch
    // the thread and the list every 5s so new replies appear without websockets.
    useEffect(() => {
        if (!activeConversation) return undefined;
        const tick = () => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['activeConversation', 'conversations'], preserveScroll: true, preserveState: true });
            }
        };
        const id = setInterval(tick, 5000);
        return () => clearInterval(id);
    }, [activeConversation?.id]);

    const startSupport = () => router.post(route('chat.start-support'));

    return (
        <MainLayout title="Messages">
            <Head title="Messages" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Messages</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Chat with owners about listings, or reach ZimRent support.</p>
                </div>
                <button onClick={startSupport} className="inline-flex items-center gap-2 rounded-xl border border-primary/30 px-4 py-2 text-sm font-bold text-primary transition-colors hover:bg-primary hover:text-white">
                    <LifeBuoy className="h-4 w-4" /> Message support
                </button>
            </div>

            {conversations.length === 0 ? (
                <EmptyState
                    icon={MessageSquareText}
                    title="No conversations yet"
                    description="Message an owner from any listing, or start a chat with ZimRent support."
                />
            ) : (
                <div className="surface grid min-h-[32rem] grid-cols-1 overflow-hidden lg:grid-cols-[20rem_1fr]">
                    {/* Conversation list */}
                    <div className={cn('flex flex-col border-border lg:border-r', activeConversation && 'hidden lg:flex')}>
                        <div className="border-b border-border px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-muted-foreground">
                            {conversations.length} {conversations.length === 1 ? 'conversation' : 'conversations'}
                        </div>
                        <ul className="flex-1 overflow-y-auto">
                            {conversations.map((conversation) => {
                                const peer = peerOf(conversation, currentUserId);
                                const active = activeConversation?.id === conversation.id;
                                const preview = conversation.latest_message?.body;
                                return (
                                    <li key={conversation.id}>
                                        <Link
                                            href={route('chat.show', conversation.id)}
                                            preserveScroll
                                            className={cn('flex items-center gap-3 border-b border-border px-4 py-3 transition-colors hover:bg-muted/60', active && 'bg-muted')}
                                        >
                                            {conversation.type === 'support' ? (
                                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"><LifeBuoy className="h-5 w-5" /></span>
                                            ) : (
                                                <Avatar user={peer.user} name={peer.name} size={40} />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="truncate text-sm font-bold text-foreground">{peer.name}</span>
                                                    {conversation.unread_count > 0 && (
                                                        <span className="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-extrabold text-white">{conversation.unread_count}</span>
                                                    )}
                                                </div>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {conversation.type === 'direct' && conversation.property ? conversation.property.title : preview || 'Support conversation'}
                                                </span>
                                            </div>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>

                    {/* Open thread */}
                    <div className={cn('min-h-[32rem]', !activeConversation && 'hidden lg:block')}>
                        {activeConversation ? (
                            <Thread conversation={activeConversation} currentUserId={currentUserId} />
                        ) : (
                            <div className="grid h-full place-items-center p-8 text-center">
                                <div>
                                    <Home className="mx-auto h-10 w-10 text-muted-foreground/40" />
                                    <p className="mt-3 text-sm text-muted-foreground">Select a conversation to read and reply.</p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </MainLayout>
    );
}
