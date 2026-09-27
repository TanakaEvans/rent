import { useCallback, useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { MessageCircle, X, LifeBuoy, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

const safeRoute = (name, params = {}) => {
    try {
        return route(name, params);
    } catch {
        return null;
    }
};

/**
 * Floating chat launcher, shown on every page. For signed-in users it shows an
 * unread badge and a panel listing recent conversations plus a "Message
 * support" button (data from the JSON `chat.unread` endpoint, light polling —
 * no websockets). For guests it still shows the bubble, and opening it invites
 * them to sign in, since messaging owners and support requires an account.
 */
export default function ChatWidget() {
    const user = usePage().props.auth?.user;
    const [open, setOpen] = useState(false);
    const [count, setCount] = useState(0);
    const [conversations, setConversations] = useState([]);

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

    if (!user && !loginUrl) return null;

    const peerName = (conversation) => {
        if (conversation.type === 'support') return 'ZimRent Support';
        const other = (conversation.participants || []).find((p) => p.id !== user.id);
        return other?.name || 'ZimRent user';
    };

    const openConversation = (id) => {
        setOpen(false);
        router.visit(route('chat.show', id));
    };

    const messageSupport = () => {
        setOpen(false);
        router.post(route('chat.start-support'));
    };

    return (
        <div className="fixed bottom-5 right-5 z-[60] flex flex-col items-end">
            {open && (
                <div className="mb-3 w-[min(88vw,22rem)] overflow-hidden rounded-2xl border border-border bg-card shadow-2xl animate-fade-up">
                    <div className="flex items-center justify-between bg-primary px-4 py-3 text-primary-foreground">
                        <span className="text-sm font-extrabold">Messages</span>
                        <button onClick={() => setOpen(false)} aria-label="Close messages" className="grid h-7 w-7 place-items-center rounded-lg hover:bg-white/15">
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    {!user ? (
                        <div className="px-5 py-8 text-center">
                            <span className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-primary/10 text-primary">
                                <MessageCircle className="h-6 w-6" />
                            </span>
                            <p className="mt-3 text-sm font-extrabold text-foreground">Chat with owners &amp; support</p>
                            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                Sign in to message property owners directly — no agent — and reach ZimRent support.
                            </p>
                            <div className="mt-4 flex flex-col gap-2">
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
                    ) : (
                    <>
                    <div className="max-h-80 overflow-y-auto">
                        {conversations.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-muted-foreground">No conversations yet.</p>
                        ) : (
                            <ul>
                                {conversations.map((conversation) => (
                                    <li key={conversation.id}>
                                        <button
                                            type="button"
                                            onClick={() => openConversation(conversation.id)}
                                            className="flex w-full items-center gap-3 border-b border-border px-4 py-3 text-left transition-colors hover:bg-muted/60"
                                        >
                                            <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-full', conversation.type === 'support' ? 'bg-primary/10 text-primary' : 'brand-gradient text-white')}>
                                                {conversation.type === 'support' ? <LifeBuoy className="h-4.5 w-4.5" /> : peerName(conversation).charAt(0).toUpperCase()}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-center justify-between gap-2">
                                                    <span className="truncate text-sm font-bold text-foreground">{peerName(conversation)}</span>
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
                            Open messages <ChevronRight className="h-3.5 w-3.5" />
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
