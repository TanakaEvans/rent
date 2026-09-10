import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Home,
    LayoutDashboard,
    Users,
    Briefcase,
    ShieldCheck,
    ListPlus,
    ListMinus,
    ListChecks,
    UserCog,
    Building2,
    Building,
    Network,
    Blocks,
    CreditCard,
    Banknote,
    Settings2,
    LogOut,
    ChevronDown,
    CheckCircle2,
    AlertCircle,
    TriangleAlert,
    X,
    BarChart3,
    Flag,
    Megaphone,
    Wrench,
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
        'group relative flex items-center gap-3 rounded-lg px-3.5 py-2 text-sm font-medium transition-all duration-200',
        active ? 'bg-sidebar-accent text-emerald-300' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/60 hover:text-white'
    );

const activeBar = (active) => active && <span className="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full brand-gradient" />;

const navSections = [
    {
        label: 'Main',
        items: [
            { label: 'Admin Dashboard', icon: LayoutDashboard, route: 'admin.dashboard', pattern: 'admin.dashboard' },
            { label: 'Marketplace', icon: Home, route: 'home', pattern: 'home' },
            { label: 'Marketplace Analytics', icon: BarChart3, route: 'admin.marketplace.analytics', pattern: 'admin.marketplace.analytics' },
            { label: 'Marketplace Reports', icon: Flag, route: 'admin.marketplace.reports.index', pattern: 'admin.marketplace.reports.*' },
            { label: 'Maintenance Escalations', icon: Wrench, route: 'admin.maintenance.escalations.index', pattern: 'admin.maintenance.escalations.*' },
            { label: 'Contractor Registry', icon: Briefcase, route: 'admin.contractors.index', pattern: 'admin.contractors.*' },
        ],
    },
    {
        label: 'Users & Employees',
        items: [
            { label: 'System Users', icon: Users, route: 'auth.users.index', pattern: 'auth.users.*' },
            { label: 'Employees', icon: Briefcase, route: 'admin.employees.index', pattern: 'admin.employees.*' },
        ],
    },
    {
        label: 'Rights & Roles',
        items: [
            { label: 'User Roles', icon: ShieldCheck, route: 'auth.roles.index', pattern: 'auth.roles.*' },
            { label: 'Bulk Assign Roles', icon: ListPlus, route: 'auth.roles.bulk-assign', pattern: 'auth.roles.bulk-assign' },
            { label: 'Bulk Remove Roles', icon: ListMinus, route: 'auth.roles.bulk-remove', pattern: 'auth.roles.bulk-remove' },
            { label: 'Users with Roles', icon: ListChecks, route: 'auth.roles.users-report', pattern: 'auth.roles.users-report' },
            { label: 'Auth Management', icon: UserCog, route: 'auth.management', pattern: 'auth.management' },
        ],
    },
    {
        label: 'Company Configuration',
        items: [
            { label: 'Company Details', icon: Building2, route: 'admin.company.index', pattern: 'admin.company.*' },
            { label: 'Branches', icon: Building, route: 'admin.branches.index', pattern: 'admin.branches.*' },
            { label: 'Departments', icon: Network, route: 'admin.departments.index', pattern: 'admin.departments.*' },
            { label: 'Sections', icon: Blocks, route: 'admin.sections.index', pattern: 'admin.sections.*' },
        ],
    },
    {
        label: 'Subscriptions & Billing',
        items: [
            { label: 'Subscription Plans', icon: CreditCard, route: 'admin.subscriptions.plans.index', pattern: 'admin.subscriptions.plans.*' },
            { label: 'Payment Approvals', icon: Banknote, route: 'admin.rent.payments.index', pattern: 'admin.rent.payments.*' },
            { label: 'Configuration Centre', icon: Settings2, route: 'admin.configuration.index', pattern: 'admin.configuration.*' },
            { label: 'Ad Placements', icon: Megaphone, route: 'admin.advertising.index', pattern: 'admin.advertising.*' },
        ],
    },
];

const toastStyles = {
    success: { icon: CheckCircle2, classes: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
    error: { icon: AlertCircle, classes: 'border-rose-200 bg-rose-50 text-rose-800' },
    warning: { icon: TriangleAlert, classes: 'border-amber-200 bg-amber-50 text-amber-800' },
};

export default function AdminLayout({ children, title = 'Dashboard', auth, flash }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const [toasts, setToasts] = useState([]);

    useEffect(() => {
        const entries = Object.entries(flash || {}).filter(([, v]) => v);
        if (!entries.length) return;
        setToasts(entries.map(([type, message]) => ({ id: `${type}-${Date.now()}`, type: type === 'success' ? 'success' : type === 'error' ? 'error' : 'warning', message })));
        const t = setTimeout(() => setToasts([]), 4600);
        return () => clearTimeout(t);
    }, [flash]);

    useEffect(() => {
        const onClick = (e) => {
            if (!e.target.closest('.profile-pop')) setProfileOpen(false);
        };
        document.addEventListener('click', onClick);
        return () => document.removeEventListener('click', onClick);
    }, []);

    const logout = () => router.post(route('logout'));
    const roles = (auth?.user?.roles || []).map((r) => r.name);

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
                        <button onClick={() => setSidebarOpen(false)} aria-label="Close menu" className="grid h-9 w-9 place-items-center rounded-lg text-sidebar-foreground/70 hover:bg-sidebar-accent lg:hidden">
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                        {navSections.map((section) => (
                            <div key={section.label}>
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

                    <div className="border-t border-sidebar-border p-4">
                        <div className="flex items-center gap-3 rounded-xl bg-sidebar-accent/70 p-3">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-sm font-extrabold text-white shadow-inner">
                                {(auth?.user?.name || 'A').charAt(0).toUpperCase()}
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-bold text-white">{auth?.user?.name}</div>
                                <div className="truncate text-[11px] text-sidebar-foreground/55">{roles.length ? roles.join(' · ') : 'Member'}</div>
                            </div>
                            <button onClick={logout} aria-label="Sign out" className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-sidebar-foreground/60 transition-colors hover:bg-rose-500/15 hover:text-rose-300">
                                <LogOut className="h-4.5 w-4.5" />
                            </button>
                        </div>
                    </div>
                </nav>

                {sidebarOpen && <div className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden" onClick={() => setSidebarOpen(false)} />}

                <div className="transition-all duration-300 lg:ml-72">
                    <header className="glass sticky top-0 z-30 border-b border-white/10 shadow-sm">
                        <div className="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
                            <div className="flex min-w-0 items-center gap-3">
                                <button onClick={() => setSidebarOpen(true)} aria-label="Open menu" className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-border bg-card text-foreground shadow-sm lg:hidden">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                                </button>
                                <div className="min-w-0">
                                    <h1 className="truncate text-lg font-extrabold tracking-tight text-foreground sm:text-xl">{title}</h1>
                                    <p className="hidden text-[11px] font-medium text-muted-foreground sm:block">Platform Administration Console</p>
                                </div>
                            </div>

                            <div className="relative profile-pop flex shrink-0 items-center gap-2">
                                <button onClick={() => setProfileOpen((v) => !v)} className="flex items-center gap-2.5 rounded-full border border-white/40 bg-white/70 py-1.5 pl-1.5 pr-3 shadow-sm transition-colors hover:bg-white">
                                    <span className="grid h-8 w-8 place-items-center rounded-full brand-gradient text-sm font-extrabold text-white">
                                        {(auth?.user?.name || 'D').charAt(0).toUpperCase()}
                                    </span>
                                    <span className="hidden max-w-32 truncate text-sm font-semibold text-foreground sm:block">{auth?.user?.name}</span>
                                    <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', profileOpen && 'rotate-180')} />
                                </button>

                                {profileOpen && (
                                    <div className="surface absolute right-0 top-full mt-2 w-64 animate-fade-up">
                                        <div className="border-b border-border px-4 py-3">
                                            <div className="truncate text-sm font-bold text-foreground">{auth?.user?.name}</div>
                                            <div className="truncate text-xs text-muted-foreground">{auth?.user?.email}</div>
                                        </div>
                                        <button onClick={logout} className="m-2 flex w-[calc(100%-1rem)] items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-semibold text-rose-600 transition-colors hover:bg-rose-50">
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