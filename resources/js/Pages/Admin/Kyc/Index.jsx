import { Head, router } from '@inertiajs/react';
import { IdCard, CheckCircle2, Ban, Undo2, Download, ShieldCheck } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatCard from '@/Components/Shared/StatCard';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import { Button } from '@/Components/ui/button';

// Colours for the derived KYC tier (none | basic | full) shown per row.
const tierPill = {
    full: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    basic: 'border-sky-200 bg-sky-50 text-sky-700',
    none: 'border-border bg-muted/40 text-muted-foreground',
};

const tierLabel = (options, key) => options?.tiers?.find((t) => t.key === key)?.label || key;

const typeLabel = (options, key) => options?.kyc_types?.find((t) => t.key === key)?.label || key;

const formatBytes = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export default function AdminKycIndex({ rows = [], activeStatus = null, counts = {}, options = {} }) {
    const items = rows.data || [];
    const page = rows.current_page || 1;
    const lastPage = rows.last_page || 1;

    const applyFilter = (status) => {
        router.get(route('admin.kyc.index'), { status: status || undefined }, { preserveScroll: true, preserveState: true });
    };

    const decide = (row, action) => {
        const withNote = ['reject', 'revoke'].includes(action) && !['rejected'].includes(row.status);
        const note = withNote ? window.prompt(action === 'reject' ? 'Rejection note (optional):' : 'Reason for revoking (optional):') : null;
        if (withNote && note === null) return;
        router.post(
            route(`admin.kyc.${action}`, { document: row.id }),
            { note: note || undefined },
            { preserveScroll: true }
        );
    };

    const download = (row) => {
        window.open(route(`admin.kyc.download`, { document: row.id }), '_blank', 'noopener');
    };

    const statCards = [
        { key: 'pending', label: 'Pending review', value: String(counts.pending ?? 0), icon: ShieldCheck, tone: 'amber' },
        { key: 'approved', label: 'Approved', value: String(counts.approved ?? 0), icon: CheckCircle2, tone: 'emerald' },
        { key: 'rejected', label: 'Rejected', value: String(counts.rejected ?? 0), icon: Ban, tone: 'rose' },
    ];

    const filters = [
        { key: '', label: 'All evidence' },
        ...(options.statuses || []),
    ];

    return (
        <AdminLayout title="KYC Review">
            <Head title="KYC Review" />

            <div className="mb-6">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Identity Review</h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Every decision here drives the tenant's derived badge tier: one approved document earns silver, both earn gold, and revoking one drops the ladder back down.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                {statCards.map((card) => (
                    <StatCard
                        key={card.key}
                        label={card.label}
                        value={card.value}
                        icon={card.icon}
                        tone={card.tone}
                    />
                ))}
            </div>

            <div className="mt-6 flex flex-wrap items-center gap-2 rounded-2xl border border-border bg-muted/40 px-4 py-3">
                <span className="mr-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">Review</span>
                {filters.map((f) => (
                    <button
                        key={f.key || 'all'}
                        type="button"
                        onClick={() => applyFilter(f.key)}
                        className={`rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors ${
                            (activeStatus || '') === f.key
                                ? 'bg-emerald-600 text-white shadow'
                                : 'border border-border bg-card text-muted-foreground hover:border-primary hover:text-primary'
                        }`}
                    >
                        {f.label}
                    </button>
                ))}
            </div>

            {items.length === 0 ? (
                <div className="mt-6">
                    <EmptyState
                        icon={IdCard}
                        title="Nothing to review here"
                        description="Identity evidence submitted by tenants appears here. Pick a filter above to change what you see."
                    />
                </div>
            ) : (
                <div className="surface mt-6 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/60 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <th className="px-5 py-3">Tenant</th>
                                    <th className="px-5 py-3">Document</th>
                                    <th className="px-5 py-3">KYC tier</th>
                                    <th className="px-5 py-3">Submitted</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {items.map((row) => {
                                    const pending = row.status === 'pending';
                                    const approved = row.status === 'approved';
                                    return (
                                        <tr key={row.id} className="align-middle transition-colors hover:bg-muted/40">
                                            <td className="px-5 py-4">
                                                <div className="flex items-center gap-2.5">
                                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-sm font-extrabold text-emerald-700">
                                                        {(row.user?.name || '?').charAt(0).toUpperCase()}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-bold text-foreground">{row.user?.name || 'Unknown'}</p>
                                                        <p className="truncate text-xs text-muted-foreground">{row.user?.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="text-sm font-semibold text-foreground">{typeLabel(options, row.type)}</p>
                                                <p className="max-w-[220px] truncate text-xs text-muted-foreground">{row.original_name}</p>
                                                <p className="text-[11px] font-medium text-muted-foreground">
                                                    {row.mime} · {formatBytes(row.size)}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold ${tierPill[row.kyc_tier] || tierPill.none}`}>
                                                    {tierLabel(options, row.kyc_tier)}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-xs font-semibold text-muted-foreground">
                                                {row.created_at ? new Date(row.created_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
                                            </td>
                                            <td className="px-5 py-4"><StatusBadge status={row.status} /></td>
                                            <td className="px-5 py-4">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {pending && (
                                                        <>
                                                            <Button size="sm" onClick={() => decide(row, 'approve')}>
                                                                <CheckCircle2 className="h-3.5 w-3.5" /> Approve
                                                            </Button>
                                                            <Button size="sm" variant="destructive" onClick={() => decide(row, 'reject')}>
                                                                <Ban className="h-3.5 w-3.5" /> Reject
                                                            </Button>
                                                        </>
                                                    )}
                                                    {approved && (
                                                        <Button size="sm" variant="outline" onClick={() => decide(row, 'revoke')}>
                                                            <Undo2 className="h-3.5 w-3.5" /> Revoke
                                                        </Button>
                                                    )}
                                                    <Button size="sm" variant="ghost" onClick={() => download(row)}>
                                                        <Download className="h-3.5 w-3.5" /> View
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {lastPage > 1 && (
                        <div className="flex items-center justify-between gap-3 border-t border-border px-5 py-4">
                            <p className="text-xs font-semibold text-muted-foreground">
                                Page {page} of {lastPage}
                            </p>
                            <div className="flex gap-2">
                                <Button size="sm" variant="outline" disabled={page <= 1}
                                    onClick={() => router.get(route('admin.kyc.index'), { status: activeStatus || undefined, page: page - 1 }, { preserveScroll: true })}>
                                    Previous
                                </Button>
                                <Button size="sm" variant="outline" disabled={page >= lastPage}
                                    onClick={() => router.get(route('admin.kyc.index'), { status: activeStatus || undefined, page: page + 1 }, { preserveScroll: true })}>
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