import { Head, router } from '@inertiajs/react';
import { Flag, ShieldAlert, User, Inbox, Star, CheckCircle2, Ban } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatCard from '@/Components/Shared/StatCard';
import EmptyState from '@/Components/Shared/EmptyState';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { Button } from '@/Components/ui/button';

const TRANSITIONS = {
    open: ['under_review', 'dismissed', 'resolved'],
    under_review: ['resolved', 'escalated', 'dismissed'],
    escalated: ['under_review', 'resolved'],
    resolved: [],
    dismissed: ['open'],
};

const reportCategoryLabels = {
    suspicious_listing: 'Suspicious listing',
    incorrect_information: 'Incorrect information',
    duplicate: 'Duplicate listing',
    wrong_price: 'Wrong price',
    fraud_concern: 'Fraud concern',
    already_rented: 'Already rented out',
    inappropriate_content: 'Inappropriate content',
};

const subjectLabel = (report) => (report.subject_type === 'property' ? `Property #${report.subject_id}` : `User #${report.subject_id}`);

export default function AdminMarketplaceReports({ reports = [], pagination = {}, stats = {}, statuses = {}, filters = {} }) {
    const applyFilter = (key, value) => {
        router.get(route('admin.marketplace.reports.index'), { ...filters, [key]: value || undefined }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const transition = (report, status) => {
        const closing = ['resolved', 'dismissed'].includes(status);
        let note = null;
        let hide = false;
        if (closing) {
            note = window.prompt(status === 'resolved' ? 'Resolution note (optional):' : 'Note (optional):');
            if (status === 'resolved' && report.subject_type === 'property') {
                hide = window.confirm('Also take this listing down?');
            }
        }
        router.post(
            route('admin.marketplace.reports.transition', { report: report.id, status }),
            { note: note || undefined, hide_listing: hide },
            { preserveScroll: true }
        );
    };

    const statCards = [
        { key: 'open', label: 'Open / In review', value: stats.open ?? 0, icon: Inbox, tone: 'amber', routeName: 'admin.marketplace.reports.index' },
        { key: 'escalated', label: 'Escalated', value: stats.escalated ?? 0, icon: Star, tone: 'rose', routeName: 'admin.marketplace.reports.index' },
        { key: 'resolved', label: 'Resolved', value: stats.resolved ?? 0, icon: CheckCircle2, tone: 'emerald', routeName: 'admin.marketplace.reports.index' },
        { key: 'dismissed', label: 'Dismissed', value: stats.dismissed ?? 0, icon: Ban, tone: 'slate', routeName: 'admin.marketplace.reports.index' },
    ];

    return (
        <AdminLayout title="Marketplace Reports">
            <Head title="Marketplace Reports" />

            <div className="mb-6">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Moderation Queue</h2>
                <p className="mt-1 text-sm text-muted-foreground">Reports raised by tenants and guests against marketplace listings.</p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {statCards.map((card) => <StatCard key={card.key} {...card} />)}
            </div>

            <div className="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-muted/40 px-4 py-3">
                <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Filter</span>
                <select
                    value={filters.status || ''}
                    onChange={(e) => applyFilter('status', e.target.value)}
                    className="h-10 rounded-xl border border-border bg-card px-3 text-sm font-semibold outline-none focus:border-primary"
                    aria-label="Filter by status"
                >
                    <option value="">All statuses</option>
                    {Object.entries(statuses).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                </select>
                <select
                    value={filters.priority || ''}
                    onChange={(e) => applyFilter('priority', e.target.value)}
                    className="h-10 rounded-xl border border-border bg-card px-3 text-sm font-semibold outline-none focus:border-primary"
                    aria-label="Filter by priority"
                >
                    <option value="">All priorities</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
                {Object.values({ ...filters, status: filters.status || '' }).some((v) => v) && (
                    <button
                        type="button"
                        onClick={() => router.get(route('admin.marketplace.reports.index'), {}, { preserveScroll: true, preserveState: true })}
                        className="text-xs font-bold text-primary hover:underline"
                    >
                        Clear filters
                    </button>
                )}
            </div>

            {reports.length === 0 ? (
                <div className="mt-6">
                    <EmptyState
                        icon={Flag}
                        title="No reports here"
                        description="Reports raised on listings appear here for review. Nothing needs your attention yet."
                    />
                </div>
            ) : (
                <div className="surface mt-6 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/60 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <th className="px-5 py-3">Reason</th>
                                    <th className="px-5 py-3">Subject</th>
                                    <th className="px-5 py-3">Reporter</th>
                                    <th className="px-5 py-3">Priority</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {reports.map((report) => {
                                    const next = TRANSITIONS[report.status] || [];
                                    return (
                                        <tr key={report.id} className="align-top transition-colors hover:bg-muted/40">
                                            <td className="max-w-[260px] px-5 py-4">
                                                <p className="font-bold text-foreground">
                                                    {reportCategoryLabels[report.category] || report.category.replace(/_/g, ' ')}
                                                </p>
                                                <p className="mt-1 line-clamp-2 text-xs leading-5 text-muted-foreground">{report.description}</p>
                                                <p className="mt-1.5 text-[11px] font-medium text-muted-foreground">
                                                    {new Date(report.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-xs font-semibold text-muted-foreground">{subjectLabel(report)}</td>
                                            <td className="px-5 py-4">
                                                {report.reporter ? (
                                                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-foreground">
                                                        <User className="h-3.5 w-3.5 text-primary" /> {report.reporter.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-xs font-medium text-muted-foreground">Guest</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4"><StatusBadge status={report.priority} /></td>
                                            <td className="px-5 py-4"><StatusBadge status={report.status} /></td>
                                            <td className="px-5 py-4">
                                                {next.length === 0 ? (
                                                    <span className="text-xs font-semibold text-muted-foreground">Closed</span>
                                                ) : (
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {next.map((status) => (
                                                            <Button
                                                                key={status}
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => transition(report, status)}
                                                                className="text-xs capitalize"
                                                            >
                                                                <ShieldAlert className="h-3.5 w-3.5" /> {status.replace(/_/g, ' ')}
                                                            </Button>
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between gap-3 border-t border-border px-5 py-4">
                            <p className="text-xs font-semibold text-muted-foreground">
                                Page {pagination.current_page} of {pagination.last_page}
                            </p>
                            <div className="flex gap-2">
                                <Button size="sm" variant="outline" disabled={pagination.current_page <= 1}
                                    onClick={() => router.get(route('admin.marketplace.reports.index'), { ...filters, page: pagination.current_page - 1 }, { preserveScroll: true })}>
                                    Previous
                                </Button>
                                <Button size="sm" variant="outline" disabled={pagination.current_page >= pagination.last_page}
                                    onClick={() => router.get(route('admin.marketplace.reports.index'), { ...filters, page: pagination.current_page + 1 }, { preserveScroll: true })}>
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}