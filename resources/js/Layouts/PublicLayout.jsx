import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import Brand from '@/Components/Shared/Brand';
import { cn } from '@/lib/utils';
import { dashboardRouteFor } from '@/lib/roles';

const ctaPitch = 'inline-flex items-center justify-center gap-2 rounded-full bg-pitch font-semibold text-brand transition-colors hover:bg-white';

/** Purple top bar + light footer shared by public pages outside the marketplace (e.g. the Help Center). */
export default function PublicLayout({ title, children }) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const user = auth?.user;
    const dashboardHref = user ? route(dashboardRouteFor(user.roles)) : route('login');
    const links = [
        [route('home'), 'Browse homes'],
        [route('help.index'), 'Help Center'],
    ];

    return (
        <>
            <Head title={title} />
            <div className="flex min-h-screen flex-col bg-white text-slate-900">
                <header className="sticky top-0 z-50 border-b border-white/10 bg-brand text-white">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                        <Link href={route('home')} aria-label="ZimRent home"><Brand dark size={32} /></Link>
                        <nav className="hidden items-center gap-8 md:flex">
                            {links.map(([href, label]) => (
                                <Link key={href} href={href} className="text-sm font-medium text-white/75 transition-colors hover:text-white">{label}</Link>
                            ))}
                        </nav>
                        <div className="hidden items-center gap-5 sm:flex">
                            {user ? (
                                <Link href={dashboardHref} className={cn(ctaPitch, 'h-9 px-4 text-sm')}>My dashboard</Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className="text-sm font-medium text-white/75 transition-colors hover:text-white">Sign in</Link>
                                    <Link href={route('register')} className={cn(ctaPitch, 'h-9 px-4 text-sm')}>Create account</Link>
                                </>
                            )}
                        </div>
                        <button type="button" className="grid h-10 w-10 place-items-center rounded-full text-white hover:bg-white/10 sm:hidden" onClick={() => setOpen((v) => !v)} aria-label="Toggle navigation" aria-expanded={open}>
                            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                    {open && (
                        <div className="border-t border-white/10 px-4 pb-4 pt-2 sm:hidden">
                            {links.map(([href, label]) => (
                                <Link key={href} href={href} className="block border-b border-white/10 py-3.5 text-[15px] text-white">{label}</Link>
                            ))}
                            {!user && <Link href={route('login')} className="block border-b border-white/10 py-3.5 text-[15px] text-white">Sign in</Link>}
                            <Link href={user ? dashboardHref : route('register')} className={cn(ctaPitch, 'mt-4 h-11 w-full text-sm')}>{user ? 'My dashboard' : 'Create account'}</Link>
                        </div>
                    )}
                </header>

                <main className="flex-1">{children}</main>

                <footer className="border-t border-slate-200 bg-slate-100 text-sm">
                    <div className="mx-auto flex max-w-7xl flex-col justify-between gap-4 px-4 py-8 text-slate-500 sm:flex-row sm:items-center sm:px-6 lg:px-8">
                        <p>© {new Date().getFullYear()} ZimRent. Rent directly. Live simply.</p>
                        <div className="flex gap-6">
                            <Link href={route('home')} className="hover:text-slate-900 hover:underline">Browse homes</Link>
                            <Link href={route('help.index')} className="hover:text-slate-900 hover:underline">Help Center</Link>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
