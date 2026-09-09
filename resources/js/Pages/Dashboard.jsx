import { Head } from '@inertiajs/react';
import { Users, UserCheck, ShieldCheck, Briefcase, Building2, Sparkles } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatCard from '@/Components/Shared/StatCard';
import { cn } from '@/lib/utils';

const roleBadge = (color) =>
    cn(
        'inline-flex items-center rounded-md px-2 py-1 text-[11px] font-bold',
        color
    );

const roleColors = {
    Superuser: roleBadge('bg-emerald-100 text-emerald-700'),
    Admin: roleBadge('bg-teal-100 text-teal-700'),
    Owner: roleBadge('bg-amber-100 text-amber-700'),
    Tenant: roleBadge('bg-sky-100 text-sky-700'),
    Staff: roleBadge('bg-violet-100 text-violet-700'),
};

export default function Dashboard({ stats, recent_users = [] }) {
    const cards = [
        { key: 'total_users', label: 'Total Users', value: stats?.total_users ?? 0, icon: Users, tone: 'emerald' },
        { key: 'active_users', label: 'Active Users', value: stats?.active_users ?? 0, icon: UserCheck, tone: 'teal' },
        { key: 'total_roles', label: 'Roles', value: stats?.total_roles ?? 0, icon: ShieldCheck, tone: 'violet' },
        { key: 'total_employees', label: 'Employees', value: stats?.total_employees ?? 0, icon: Briefcase, tone: 'amber' },
        { key: 'total_branches', label: 'Branches', value: stats?.total_branches ?? 0, icon: Building2, tone: 'rose' },
    ];

    return (
        <MainLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="mb-7 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span className="kicker border-primary/25 bg-primary/10 text-primary">
                        <Sparkles className="h-3.5 w-3.5" /> Workspace overview
                    </span>
                    <h2 className="mt-2 text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Welcome to Dzimba</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Your platform for direct property rentals and property management.</p>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {cards.map((card) => (
                    <StatCard key={card.key} {...card} />
                ))}
            </div>

            <div className="surface mt-8 overflow-hidden">
                <div className="flex items-center gap-2 border-b border-border px-5 py-4">
                    <Users className="h-4 w-4 text-primary" />
                    <h3 className="text-base font-bold tracking-tight">Recent Users</h3>
                </div>
                {recent_users.length === 0 ? (
                    <p className="px-5 py-10 text-center text-sm text-muted-foreground">No users yet.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/60 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <th className="px-5 py-3">Name</th>
                                    <th className="hidden px-5 py-3 sm:table-cell">Email</th>
                                    <th className="px-5 py-3">Roles</th>
                                    <th className="hidden px-5 py-3 md:table-cell">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {recent_users.map((user) => (
                                    <tr key={user.id} className="transition-colors hover:bg-muted/40">
                                        <td className="px-5 py-3.5 font-semibold text-foreground">{user.name}</td>
                                        <td className="hidden px-5 py-3.5 text-muted-foreground sm:table-cell">{user.email}</td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex flex-wrap gap-1.5">
                                                {(user.roles || []).map((role) => (
                                                    <span key={role.id} className={roleColors[role.name] || roleBadge('bg-slate-100 text-slate-700')}>
                                                        {role.name}
                                                    </span>
                                                ))}
                                                {(user.roles || []).length === 0 && (
                                                    <span className={roleBadge('bg-slate-100 text-slate-500')}>No role</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="hidden px-5 py-3.5 md:table-cell">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold',
                                                    user.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
                                                )}
                                            >
                                                <span className={cn('h-1.5 w-1.5 rounded-full', user.status === 'active' ? 'bg-emerald-500' : 'bg-rose-500')} />
                                                {user.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </MainLayout>
    );
}