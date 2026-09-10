import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Home,
    LayoutDashboard,
    Building2,
    KeyRound,
    Users,
    ShieldCheck,
    ListChecks,
    LogOut,
    ChevronDown,
    CheckCircle2,
    AlertCircle,
    TriangleAlert,
    X,
    Heart,
    MessageSquareText,
    CalendarClock,
    Bell,
    ClipboardCheck,
    FileSignature,
    FolderOpen,
    BadgeDollarSign,
    Receipt,
    Save,
    Flag,
    BarChart3,
    Megaphone,
} from 'lucide-react';
import Brand from '@/Components/Shared/Brand';
import { cn } from '@/lib/utils';

const safeRoute = (name, params = {}) => {
    try {
        return route(name, params);
    } catch {
        return '#';
    }
};

const isCurrentRoute = (pattern) => {
    try {
        return route().current(pattern);
    } catch {
        return false;
    }
};

const navLinkClass = (active) =>
    cn(
        'group relative flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium transition-all duration-200',
        active
            ? 'bg-sidebar-accent text-emerald-300 shadow-[inset_0_0_0_1px_rgba(255,255,255,0.04)]'
            : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/60 hover:text-white'
    );

const activeBar = (active) =>
    active && <span className="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full brand-gradient" />;

export default function MainLayout({ children, title = 'Dashboard', auth, flash, notifications = [], unreadNotificationsCount = 0 }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const [bellOpen, setBellOpen] = useState(false);
    const [toasts, setToasts] = useState([]);

    const user = auth?.user;
    const roles = (user?.roles || []).map((r) => r.name);
    const isAdmin = ['Admin', 'Superuser'].some((r) => roles.includes(r));
    const isOwner = roles.includes('Owner');
    const isTenant = roles.includes('Tenant');

    useEffect(() => {
        const entries = Object.entries(flash || {}).filter(([, v]) => v);
        if (!entries.length) return;
        const items = entries.map(([type, message]) => ({ id: `${type}-${Date.now()}`, type: type === 'success' ? 'success' : type === 'error' ? 'error' : 'warning', message }));
        setToasts(items);
        const t = setTimeout(() => setToasts([]), 4600);
        return () => clearTimeout(t);
    }, [flash]);

    useEffect(() => {
        const onClick = (e) => {
            if (!e.target.closest('.profile-pop')) setProfileOpen(false);
            if (!e.target.closest('.bell-pop')) setBellOpen(false);
        };
        document.addEventListener('click', onClick);
        return () => document.removeEventListener('click', onClick);
    }, []);

    const logout = () => router.post(route('logout'));

    const ownerSections = [];
    if (isOwner) {
        ownerSections.push(
            {
                label: 'Owner',
                items: [
                    { label: 'Owner Dashboard', icon: LayoutDashboard, route: 'owner.dashboard', pattern: 'owner.dashboard' },
                    { label: 'My Properties', icon: Building2, route: 'owner.properties.index', pattern: 'owner.properties.*' },
                    { label: 'Analytics', icon: BarChart3, route: 'owner.analytics.index', pattern: 'owner.analytics.*' },
                    { label: 'Advertising', icon: Megaphone, route: 'owner.advertising.index', pattern: 'owner.advertising.*' },
                ],
            },
            {
                label: 'Tenant Activity',
                items: [
                    { label: 'Enquiry Inbox', icon: MessageSquareText, route: 'owner.enquiries.index', pattern: 'owner.enquiries.*' },
                    { label: 'Viewing Requests', icon: CalendarClock, route: 'owner.viewings.index', pattern: 'owner.viewings.*' },
                    { label: 'Applications', icon: ClipboardCheck, route: 'owner.applications.index', pattern: 'owner.applications.*' },
                ],
            },
            {
                label: 'Billing & Documents',
                items: [
                    { label: 'Rent & Income', icon: Receipt, route: 'owner.rent.index', pattern: 'owner.rent.*' },
                    { label: 'Leases', icon: FileSignature, route: 'owner.leases.index', pattern: 'owner.leases.*' },
                    { label: 'Documents', icon: FolderOpen, route: 'owner.documents.index', pattern: 'owner.documents.*' },
                    { label: 'Plan & Billing', icon: BadgeDollarSign, route: 'owner.subscriptions.index', pattern: 'owner.subscriptions.*' },
                ],
            },
        );
    }

    const tenantSections = [];
    if (isTenant) {
        tenantSections.push(
            {
                label: 'Tenant',
                items: [
                    { label: 'Tenant Dashboard', icon: LayoutDashboard, route: 'tenant.dashboard', pattern: 'tenant.dashboard' },
                ],
            },
            {
                label: 'My Activity',
                items: [
                    { label: 'My Favourites', icon: Heart, route: 'tenant.favourites.index', pattern: 'tenant.favourites.*' },
                    { label: 'Saved Searches', icon: Save, route: 'tenant.saved-searches.index', pattern: 'tenant.saved-searches.*' },
                    { label: 'My Enquiries', icon: MessageSquareText, route: 'tenant.enquiries.index', pattern: 'tenant.enquiries.*' },
                    { label: 'My Viewings', icon: CalendarClock, route: 'tenant.viewings.index', pattern: 'tenant.viewings.*' },
                    { label: 'My Applications', icon: ClipboardCheck, route: 'tenant.applications.index', pattern: 'tenant.applications.*' },
                    { label: 'My Reports', icon: Flag, route: 'tenant.reports.index', pattern: 'tenant.reports.*' },
                ],
            },
            {
                label: 'Billing & Documents',
                items: [
                    { label: 'My Rent', icon: Receipt, route: 'tenant.rent.index', pattern: 'tenant.rent.*' },
                    { label: 'My Leases', icon: FileSignature, route: 'tenant.leases.index', pattern: 'tenant.leases.*' },
                    { label: 'My Documents', icon: FolderOpen, route: 'tenant.documents.index', pattern: 'tenant.documents.*' },
                ],
            },
        );
    }

    const adminSections = [];
    if (isAdmin) {
        adminSections.push(
            {
                label: 'Administration',
                items: [
                    { label: 'Admin Dashboard', icon: LayoutDashboard, route: 'admin.dashboard', pattern: 'admin.dashboard' },
                    { label: 'System Users', icon: Users, route: 'auth.users.index', pattern: 'auth.users.*' },
                    { label: 'User Roles', icon: ShieldCheck, route: 'auth.roles.index', pattern: 'auth.roles.*' },
                    { label: 'Users with Roles', icon: ListChecks, route: 'auth.roles.users-report', pattern: 'auth.roles.users-report' },
                    { label: 'Auth Management', icon: KeyRound, route: 'auth.management', pattern: 'auth.management' },
                ],
            },
        );
    }

    const roleSections = [...adminSections, ...ownerSections, ...tenantSections];

    const toastStyles = {
        success: { icon: CheckCircle2, classes: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
        error: { icon: AlertCircle, classes: 'border-rose-200 bg-rose-50 text-rose-800' },
        warning: { icon: TriangleAlert, classes: 'border-amber-200 bg-amber-50 text-amber-800' },
    };

    return (
        <>
            <Head title={title} />

            <div className="app-bg min-h-screen">
                <nav
                    className={cn(
                        'fixed left-0 top-0 z-50 flex h-full w-72 flex-col transition-transform duration-300 dark-sidebar',
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
                    )}
                >
                    <div className="flex items-center justify-between border-b border-sidebar-border px-5 py-5">
                        <Brand dark showText />
                        <button
                            onClick={() => setSidebarOpen(false)}
                            aria-label="Close menu"
                            className="grid h-9 w-9 place-items-center rounded-lg text-sidebar-foreground/70 hover:bg-sidebar-accent lg:hidden"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-7 overflow-y-auto px-3 py-6">
                        <div>
                            <div className="mb-2 px-3.5 text-[11px] font-bold uppercase tracking-[0.16em] text-sidebar-foreground/40">Discover</div>
                            <Link href={safeRoute('home')} className={navLinkClass(isCurrentRoute('home'))}>
                                {activeBar(isCurrentRoute('home'))}
                                <Home className="h-4.5 w-4.5 text-emerald-400" />
                                Marketplace
                            </Link>
                        </div>

                        {roleSections.length > 0 && (
                            <div>
                                {roleSections.map((section) => (
                                    <div key={section.label} className="mb-6 last:mb-0">
                                        <div className="mb-2 px-3.5 text-[11px] font-bold uppercase tracking-[0.16em] text-sidebar-foreground/40">{section.label}</div>
                                        <div className="space-y-1">
                                            {section.items.map((item) => {
                                                const active = isCurrentRoute(item.pattern);
                                                const Icon = item.icon;
                                                return (
                                                    <Link key={item.route} href={safeRoute(item.route)} className={navLinkClass(active)}>
                                                        {activeBar(active)}
                                                        <Icon className="h-4.5 w-4.5 text-emerald-400" strokeWidth={1.9} />
                                                        {item.label}
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="border-t border-sidebar-border p-4">
                        <div className="flex items-center gap-3 rounded-xl bg-sidebar-accent/70 p-3">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-sm font-extrabold text-white shadow-inner">
                                {(user?.name || 'U').charAt(0).toUpperCase()}
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-bold text-white">{user?.name}</div>
                                <div className="truncate text-[11px] text-sidebar-foreground/55">
                                    {roles.length ? roles.join(' · ') : 'Member'}
                                </div>
                            </div>
                            <button onClick={logout} aria-label="Sign out" className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-sidebar-foreground/60 transition-colors hover:bg-rose-500/15 hover:text-rose-300">
                                <LogOut className="h-4.5 w-4.5" />
                            </button>
                        </div>
                    </div>
                </nav>

                {sidebarOpen && (
                    <div className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden" onClick={() => setSidebarOpen(false)} />
                )}

                <div className="transition-all duration-300 lg:ml-72">
                    <header className="glass sticky top-0 z-30 border-b border-white/10 shadow-sm">
                        <div className="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
                            <div className="flex min-w-0 items-center gap-3">
                                <button
                                    onClick={() => setSidebarOpen(true)}
                                    aria-label="Open menu"
                                    className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-border bg-card text-foreground shadow-sm lg:hidden"
                                >
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                                </button>
                                <div className="min-w-0">
                                    <h1 className="truncate text-lg font-extrabold tracking-tight text-foreground sm:text-xl">{title}</h1>
                                    <p className="hidden text-[11px] font-medium text-muted-foreground sm:block">
                                        {roles.length ? `Signed in as ${roles.join(', ')}` : 'Dzimba Property Platform'}
                                    </p>
                                </div>
                            </div>

                            <div className="relative flex shrink-0 items-center gap-2">
                                {/* Notification bell */}
                                <div className="relative bell-pop">
                                    <button
                                        onClick={() => setBellOpen((v) => !v)}
                                        aria-label="Notifications"
                                        className="relative grid h-10 w-10 place-items-center rounded-xl border border-border bg-card text-foreground shadow-sm transition-colors hover:border-primary/40 hover:text-primary"
                                    >
                                        <Bell className="h-4.5 w-4.5" strokeWidth={1.9} />
                                        {unreadNotificationsCount > 0 && (
                                            <span className="absolute -right-1.5 -top-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-extrabold text-white shadow ring-2 ring-card">
                                                {unreadNotificationsCount}
                                            </span>
                                        )}
                                    </button>

                                    {bellOpen && (
                                        <div className="surface absolute right-0 top-full z-50 mt-2 w-[min(88vw,360px)] overflow-hidden rounded-2xl p-2 animate-fade-up">
                                            <div className="flex items-center justify-between border-b border-border px-3 pb-2.5 pt-2">
                                                <span className="text-xs font-extrabold uppercase tracking-wider text-muted-foreground">
                                                    Notifications
                                                </span>
                                                {unreadNotificationsCount > 0 && (
                                                    <span className="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-extrabold text-rose-600 ring-1 ring-rose-200">
                                                        {unreadNotificationsCount} new
                                                    </span>
                                                )}
                                            </div>

                                            {notifications.length === 0 ? (
                                                <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                                    No notifications yet.
                                                </p>
                                            ) : (
                                                <ul className="max-h-80 overflow-y-auto">
                                                    {notifications.map((n) => {
                                                        const unread = !n.read_at;
                                                        return (
                                                            <li key={n.id}>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setBellOpen(false);
                                                                        router.post(route('notifications.read', n.id), {}, {})
                                                                    }}
                                                                    className="flex w-full items-start gap-2.5 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-muted"
                                                                >
                                                                    <span className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', unread ? 'bg-emerald-500' : 'bg-muted-foreground/30')} />
                                                                    <span className="min-w-0">
                                                                        <span className={cn('block truncate text-sm', unread ? 'font-bold text-foreground' : 'font-medium text-muted-foreground')}>
                                                                            {n.data?.title}
                                                                        </span>
                                                                        {n.data?.body && (
                                                                            <span className="block truncate text-xs text-muted-foreground">{n.data.body}</span>
                                                                        )}
                                                                        <span className="mt-0.5 block text-[10px] font-semibold uppercase tracking-wide text-muted-foreground/60">
                                                                            {new Date(n.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}
                                                                        </span>
                                                                    </span>
                                                                </button>
                                                            </li>
                                                        );
                                                    })}
                                                </ul>
                                            )}

                                            <Link
                                                href={safeRoute('notifications.index')}
                                                onClick={() => setBellOpen(false)}
                                                className="mt-1 block rounded-lg border-t border-border px-3 py-2.5 text-center text-xs font-bold text-primary transition-colors hover:bg-muted"
                                            >
                                                View all notifications
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            </div>
                                <div className="relative profile-pop flex shrink-0 items-center gap-2">
                                <button
                                    onClick={() => setProfileOpen((v) => !v)}
                                    className="flex items-center gap-2.5 rounded-full border border-white/40 bg-white/70 py-1.5 pl-1.5 pr-3 shadow-sm transition-colors hover:bg-white"
                                >
                                    <span className="grid h-8 w-8 place-items-center rounded-full brand-gradient text-sm font-extrabold text-white">
                                        {(user?.name || 'D').charAt(0).toUpperCase()}
                                    </span>
                                    <span className="hidden max-w-32 truncate text-sm font-semibold text-foreground sm:block">{user?.name}</span>
                                    <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', profileOpen && 'rotate-180')} />
                                </button>

                                {profileOpen && (
                                    <div className="surface absolute right-0 top-full mt-2 w-64 p-2 animate-fade-up">
                                        <div className="border-b border-border px-3 pb-3 pt-2">
                                            <div className="truncate text-sm font-bold text-foreground">{user?.name}</div>
                                            <div className="truncate text-xs text-muted-foreground">{user?.email}</div>
                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {roles.map((r) => (
                                                    <span key={r} className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-200">
                                                        {r}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <button
                                            onClick={logout}
                                            className="mt-1 flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-semibold text-rose-600 transition-colors hover:bg-rose-50"
                                        >
                                            <LogOut className="h-4 w-4" />
                                            Sign out
                                        </button>
                                    </div>
                                )}
                                </div>
                            </div>
                    </header>

                    <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">{children}</main>
                </div>

                {toasts.length > 0 && (
                    <div className="pointer-events-none fixed right-4 top-4 z-[70] flex w-[min(92vw,380px)] flex-col gap-2">
                        {toasts.map((t) => {
                            const cfg = toastStyles[t.type];
                            const Icon = cfg.icon;
                            return (
                                <div key={t.id} className={cn('pointer-events-auto flex items-start gap-3 rounded-xl border p-3.5 shadow-lg animate-toast-in', cfg.classes)}>
                                    <Icon className="mt-0.5 h-5 w-5 shrink-0" strokeWidth={2} />
                                    <p className="flex-1 text-sm font-medium leading-snug">{t.message}</p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}