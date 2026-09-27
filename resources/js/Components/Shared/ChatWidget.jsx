import { useCallback, useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { MessageCircle, X, LifeBuoy, ChevronRight, ArrowLeft, SendHorizontal, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

const safeRoute = (name, params = {}) => {
    try {
        return route(name, params);
    } catch {
        return null;
    }
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const clockOf = (iso) => {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    } catch {
        return '';
    }
};

/**
 * Floating chat launcher, shown on every page. Signed-in users read and reply
 * to conversations inline in the panel (no page navigation) — the list and a
 * thread view live in the same popup, matching the app's SPA feel. Data comes
 * from JSON endpoints over plain fetch (light polling — no websockets). Guests
 * see the bubble too, and opening it invites them to sign in, since messaging
 * requires an account.
 */
export default function ChatWidget() {
    const user = usePage().props.auth?.user;
    const [open, setOpen] = useState(false);
    const [count, setCount] = useState(0);
    const [conversations, setConversations] = useState([]);

    // Inline thread state.
    const [active, setActive] = useState(null); // { id, peer, type, property_title }
    const [messages, setMessages] = useState([]);
    const [threadLoading, setThreadLoading] = useState(false);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const scrollRef = useRef(null);

    const endpoint = safeRoute('chat.unread');
    const loginUrl = safeRoute('login');
    const registerUrl = safeRoute('register');

    const refresh = useCallback(() => {
        if (!endpoint || document.visibilityState !== 'visible') return;
        fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (!data) return;
                setCount(data.count ?? 0);
                setConversations(data.conversations ?? []);
            })
            .catch(() => {});
    }, [endpoint]);

    useEffect(() => {
        if (!user || !endpoint) return undefined;
        refresh();
        const id = setInterval(refresh, 15000);
        const onNav = () => refresh();
        document.addEventListener('inertia:finish', onNav);
        return () => {
            clearInterval(id);
            document.removeEventListener('inertia:finish', onNav);
        };
    }, [user, endpoint, refresh]);

    // Keep the thread scrolled to the newest message.
    useEffect(() => {
        if (active && scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [messages, active, threadLoading]);

    // Poll the open thread so a reply from the other side appears live.
    useEffect(() => {
        if (!active) return undefined;
        const id = setInterval(() => loadThread(active.id, { silent: true }), 12000);
        return () => clearInterval(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [active?.id]);

    if (!user && !loginUrl) return null;

    const listPeerName = (conversation) => {
        if (conversation.type === 'support') return 'ZimRent Support';
        const other = (conversation.participants || []).find((p) => p.id !== user.id);
        return other?.name || 'ZimRent user';
    };

    const loadThread = (id, { silent = false } = {}) => {
        const url = safeRoute('chat.thread', id);
        if (!url) return;
        if (!silent) setThreadLoading(true);
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (!data) return;
                setActive({ id: data.id, peer: data.peer, type: data.type, property_title: data.property_title });
                setMessages(data.messages || []);
                if (!silent) refresh(); // opening clears unread
            })
            .catch(() => {})
            .finally(() => setThreadLoading(false));
    };

    const openConversation = (conversation) => loadThread(conversation.id);

    const backToList = () => {
        setActive(null);
        setMessages([]);
        setDraft('');
        refresh();
    };

    const messageSupport = () => {
        const url = safeRoute('chat.start-support');
        if (!url) return;
        setThreadLoading(true);
        fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            credentials: 'same-origin',
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => (data?.id ? loadThread(data.id) : setThreadLoading(false)))
            .catch(() => setThreadLoading(false));
    };

    const sendMessage = () => {
        const body = draft.trim();
        if (!body || !active || sending) return;
        const url = safeRoute('chat.message', active.id);
        if (!url) return;
        setSending(true);
        fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            credentials: 'same-origin',
            body: JSON.stringify({ body }),
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (data?.message) {
                    setMessages((prev) => [...prev, data.message]);
                    setDraft('');
                    refresh();
                }
            })
            .catch(() => {})
            .finally(() => setSending(false));
    };

    const onComposerKeyDown = (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    };

    return (
        <div className="fixed bottom-5 right-5 z-[60] flex flex-col items-end">
            {open && (
                <div className="mb-3 flex h-[min(70vh,32rem)] w-[min(88vw,22rem)] flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl animate-fade-up">
                    {/* Header */}
                    <div className="flex items-center gap-2 bg-primary px-4 py-3 text-primary-foreground">
                        {active && (
                            <button onClick={backToList} aria-label="Back to conversations" className="-ml-1 grid h-7 w-7 place-items-center rounded-lg hover:bg-white/15">
                                <ArrowLeft className="h-4 w-4" />
                            </button>
                        )}
                        <span className="min-w-0 flex-1 truncate text-sm font-extrabold">
                            {active ? active.peer : 'Messages'}
                        </span>
                        <button onClick={() => setOpen(false)} aria-label="Close messages" className="grid h-7 w-7 place-items-center rounded-lg hover:bg-white/15">
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    {!user ? (
                        /* ---- Guest: invite to sign in ---- */
                        <div className="flex flex-1 flex-col items-center justify-center px-5 py-8 text-center">
                            <span className="grid h-12 w-12 place-items-center rounded-full bg-primary/10 text-primary">
                                <MessageCircle className="h-6 w-6" />
                            </span>
                            <p className="mt-3 text-sm font-extrabold text-foreground">Chat with owners &amp; support</p>
                            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                Sign in to message property owners directly — no agent — and reach ZimRent support.
                            </p>
                            <div className="mt-4 flex w-full flex-col gap-2">
                                <button type="button" onClick={() => router.visit(loginUrl)} className="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-bold text-primary-foreground transition-colors hover:opacity-90">
                                    Sign in
                                </button>
                                {registerUrl && (
                                    <button type="button" onClick={() => router.visit(registerUrl)} className="inline-flex h-10 items-center justify-center rounded-xl border border-border px-4 text-sm font-bold text-foreground transition-colors hover:bg-muted">
                                        Create a free account
                                    </button>
                                )}
                            </div>
                        </div>
                    ) : active ? (
                        /* ---- Inline thread ---- */
                        <>
                            <div ref={scrollRef} className="flex-1 space-y-2.5 overflow-y-auto bg-muted/30 px-3.5 py-4">
                                {threadLoading && messages.length === 0 ? (
                                    <div className="flex h-full items-center justify-center text-muted-foreground">
                                        <Loader2 className="h-5 w-5 animate-spin" />
                                    </div>
                                ) : messages.length === 0 ? (
                                    <p className="px-2 py-8 text-center text-sm text-muted-foreground">
                                        No messages yet. Say hello 👋
                                    </p>
                                ) : (
                                    messages.map((m) => (
                                        <div key={m.id} className={cn('flex flex-col', m.mine ? 'items-end' : 'items-start')}>
                                            <div className={cn(
                                                'max-w-[82%] rounded-2xl px-3.5 py-2 text-sm leading-relaxed',
                                                m.mine ? 'rounded-br-sm bg-primary text-primary-foreground' : 'rounded-bl-sm bg-card text-foreground shadow-sm ring-1 ring-border',
                                            )}>
                                                {m.body}
                                            </div>
                                            <span className="mt-0.5 px-1 text-[10px] text-muted-foreground">{clockOf(m.at)}</span>
                                        </div>
                                    ))
                                )}
                            </div>
                            <div className="flex items-end gap-2 border-t border-border p-2.5">
                                <textarea
                                    value={draft}
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={onComposerKeyDown}
                                    rows={1}
                                    placeholder="Write a message…"
                                    className="max-h-28 min-h-10 flex-1 resize-none rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/20"
                                />
                                <button
                                    type="button"
                                    onClick={sendMessage}
                                    disabled={!draft.trim() || sending}
                                    aria-label="Send message"
                                    className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-40"
                                >
                                    {sending ? <Loader2 className="h-4.5 w-4.5 animate-spin" /> : <SendHorizontal className="h-4.5 w-4.5" />}
                                </button>
                            </div>
                        </>
                    ) : (
                        /* ---- Conversation list ---- */
                        <>
                            <div className="flex-1 overflow-y-auto">
                                {conversations.length === 0 ? (
                                    <p className="px-4 py-8 text-center text-sm text-muted-foreground">No conversations yet.</p>
                                ) : (
                                    <ul>
                                        {conversations.map((conversation) => (
                                            <li key={conversation.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => openConversation(conversation)}
                                                    className="flex w-full items-center gap-3 border-b border-border px-4 py-3 text-left transition-colors hover:bg-muted/60"
                                                >
                                                    <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-full', conversation.type === 'support' ? 'bg-primary/10 text-primary' : 'brand-gradient text-white')}>
                                                        {conversation.type === 'support' ? <LifeBuoy className="h-4.5 w-4.5" /> : listPeerName(conversation).charAt(0).toUpperCase()}
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex items-center justify-between gap-2">
                                                            <span className="truncate text-sm font-bold text-foreground">{listPeerName(conversation)}</span>
                                                            {conversation.unread_count > 0 && (
                                                                <span className="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-extrabold text-white">{conversation.unread_count}</span>
                                                            )}
                                                        </span>
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {conversation.type === 'direct' && conversation.property_title ? conversation.property_title : conversation.preview || 'Support conversation'}
                                                        </span>
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            <div className="flex items-center justify-between gap-2 border-t border-border p-3">
                                <button onClick={messageSupport} className="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 px-3 py-2 text-xs font-bold text-primary transition-colors hover:bg-primary hover:text-white">
                                    <LifeBuoy className="h-3.5 w-3.5" /> Message support
                                </button>
                                <button onClick={() => { setOpen(false); router.visit(route('chat.index')); }} className="inline-flex items-center gap-1 text-xs font-bold text-muted-foreground hover:text-primary">
                                    Open full messages <ChevronRight className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </>
                    )}
                </div>
            )}

            <button
                onClick={() => { setOpen((v) => !v); if (!open) refresh(); }}
                aria-label="Messages"
                className="relative grid h-14 w-14 place-items-center rounded-full brand-gradient text-white shadow-xl transition-transform hover:scale-105"
            >
                <MessageCircle className="h-6 w-6" strokeWidth={2} />
                {count > 0 && (
                    <span className="absolute -right-1 -top-1 grid h-6 min-w-6 place-items-center rounded-full bg-rose-500 px-1 text-[11px] font-extrabold text-white ring-2 ring-white">
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </button>
        </div>
    );
}
