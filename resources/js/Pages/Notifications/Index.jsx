import { Head, router } from '@inertiajs/react';
import { BellRing, CheckCheck, ChevronRight } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { cn } from '@/lib/utils';

const timeAgo = (value) => {
    if (!value) return '';
    const seconds = Math.floor((Date.now() - new Date(value).getTime()) / 1000);
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
};

export default function Notifications({ notifications = [], unreadCount = 0 }) {
    const open = (id) => {
        router.post(route('notifications.read', id), {}, { preserveScroll: false });
    };

    const markAllRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
    };

    return (
        <MainLayout title="Notifications">
            <Head title="Notifications" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Notifications</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {unreadCount > 0 ? `${unreadCount} unread — click an item to go straight to it.` : 'You are all caught up.'}
                    </p>
                </div>
                {unreadCount > 0 && notifications.length > 0 && (
                    <button
                        type="button"
                        onClick={markAllRead}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3.5 py-2 text-xs font-bold text-muted-foreground transition-colors hover:border-emerald-300 hover:text-emerald-700"
                    >
                        <CheckCheck className="h-4 w-4" /> Mark all as read
                    </button>
                )}
            </div>

            {notifications.length === 0 ? (
                <EmptyState
                    icon={BellRing}
                    title="No notifications yet"
                    description="When tenants enquire or book viewings on your properties, or owners reply, it will show up here."
                />
            ) : (
                <ul className="space-y-2">
                    {notifications.map((notification) => {
                        const unread = !notification.read_at;
                        return (
                            <li key={notification.id}>
                                <button
                                    type="button"
                                    onClick={() => open(notification.id)}
                                    className={cn(
                                        'surface flex w-full items-center justify-between gap-4 p-4 text-left transition-colors hover:border-primary/30 sm:p-5',
                                        unread && 'border-l-4 border-l-primary'
                                    )}
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className={cn('text-sm', unread ? 'font-extrabold text-foreground' : 'font-semibold text-muted-foreground')}>
                                                {notification.data?.title}
                                            </h3>
                                            {unread && <StatusBadge status="new" />}
                                        </div>
                                        {notification.data?.body && (
                                            <p className="mt-1 truncate text-sm text-muted-foreground">{notification.data.body}</p>
                                        )}
                                        <p className="mt-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/70">
                                            {timeAgo(notification.created_at)}
                                        </p>
                                    </div>
                                    <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground/60" />
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </MainLayout>
    );
}