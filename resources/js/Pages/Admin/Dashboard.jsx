import { Head } from '@inertiajs/react';
import { ShieldCheck, Home, KeyRound, BadgeCheck, FileText, Users, UserCheck, Briefcase, Building2, Network, LayoutDashboard } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatCard from '@/Components/Shared/StatCard';

export default function AdminDashboard({ stats = {} }) {
    const marketplaceCards = [
        { key: 'total_properties', label: 'Total Properties', value: stats.total_properties ?? 0, icon: Home, tone: 'emerald' },
        { key: 'listed_properties', label: 'Live Listings', value: stats.listed_properties ?? 0, icon: KeyRound, tone: 'teal' },
        { key: 'verified_properties', label: 'Verified Properties', value: stats.verified_properties ?? 0, icon: BadgeCheck, tone: 'sky' },
        { key: 'total_applications', label: 'Applications', value: stats.total_applications ?? 0, icon: FileText, tone: 'violet' },
    ];

    const platformCards = [
        { key: 'total_users', label: 'Total Users', value: stats.total_users ?? 0, icon: Users, tone: 'indigo' },
        { key: 'active_users', label: 'Active Users', value: stats.active_users ?? 0, icon: UserCheck, tone: 'emerald' },
        { key: 'total_roles', label: 'User Roles', value: stats.total_roles ?? 0, icon: ShieldCheck, tone: 'amber' },
        { key: 'total_employees', label: 'Employees', value: stats.total_employees ?? 0, icon: Briefcase, tone: 'rose' },
        { key: 'total_branches', label: 'Branches', value: stats.total_branches ?? 0, icon: Building2, tone: 'teal' },
        { key: 'total_departments', label: 'Departments', value: stats.total_departments ?? 0, icon: Network, tone: 'sky' },
    ];

    return (
        <AdminLayout title="Admin Dashboard">
            <Head title="Admin Dashboard" />

            <div className="mb-7">
                <span className="kicker border-primary/25 bg-primary/10 text-primary">
                    <LayoutDashboard className="h-3.5 w-3.5" /> Platform overview
                </span>
                <h2 className="mt-2 text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Administration Centre</h2>
                <p className="mt-1 text-sm text-muted-foreground">Monitor the marketplace, verify listings, and manage the platform.</p>
            </div>

            <div className="mb-6 flex items-center gap-3">
                <span className="h-5 w-1 rounded-r-full brand-gradient" />
                <h3 className="text-sm font-extrabold uppercase tracking-[0.14em] text-foreground">Marketplace Health</h3>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {marketplaceCards.map((card) => (
                    <StatCard key={card.key} {...card} />
                ))}
            </div>

            <div className="mt-9 mb-6 flex items-center gap-3">
                <span className="h-5 w-1 rounded-r-full brand-gradient" />
                <h3 className="text-sm font-extrabold uppercase tracking-[0.14em] text-foreground">Platform Operations</h3>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {platformCards.map((card) => (
                    <StatCard key={card.key} {...card} />
                ))}
            </div>
        </AdminLayout>
    );
}