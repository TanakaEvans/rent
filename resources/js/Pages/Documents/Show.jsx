import { Head, Link, usePage } from '@inertiajs/react';
import { FileText, Download, ArrowLeft } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';

const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
const bytes = (value) => `${(Number(value || 0) / 1024).toFixed(1)} KB`;

export default function DocumentsShow({ document }) {
    const { user } = usePage().props.auth;
    const roles = (user?.roles || []).map((r) => r.name);
    const backRoute = roles.includes('Owner')
        ? route('owner.documents.index')
        : roles.includes('Tenant')
          ? route('tenant.documents.index')
          : route('home');

    return (
        <MainLayout title={document?.name || 'Document'}>
            <Head title={document?.name || 'Document'} />

            <div className="mx-auto max-w-3xl">
                <Link
                    href={backRoute}
                    className="mb-5 inline-flex items-center gap-1.5 text-sm font-bold text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" /> Back to documents
                </Link>

                <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="flex items-center gap-2 text-2xl font-extrabold tracking-tight sm:text-3xl">
                            <FileText className="h-7 w-7 text-primary" /> {document?.name}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Residency snapshot · version {document?.version} · {document?.mime}
                        </p>
                    </div>
                    <a
                        href={route('documents.download', document?.id)}
                        className="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground transition hover:bg-primary/90"
                    >
                        <Download className="h-4 w-4" /> Download
                    </a>
                </div>

                <div className="mb-5 grid gap-3 rounded-2xl border border-border bg-card p-4 text-sm sm:grid-cols-3">
                    <div>
                        <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Generated</p>
                        <p className="mt-0.5 font-bold text-foreground">{fmt(document?.created_at)}</p>
                    </div>
                    <div>
                        <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Size</p>
                        <p className="mt-0.5 font-bold text-foreground">{bytes(document?.size)}</p>
                    </div>
                    <div>
                        <p className="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Lease</p>
                        <p className="mt-0.5 truncate font-mono font-bold text-foreground">{document?.lease?.lease_no || '—'}</p>
                    </div>
                </div>

                {document?.lease && (
                    <p className="mb-5 text-sm text-muted-foreground">
                        {document.lease.property?.title} ·{' '}
                        {[document.lease.property?.suburb, document.lease.property?.city].filter(Boolean).join(', ') || 'Location on request'}
                    </p>
                )}

                <pre className="whitespace-pre-wrap rounded-2xl border border-border bg-slate-950 p-5 font-mono text-[13px] leading-relaxed text-slate-100 shadow-inner">
                    {document?.content}
                </pre>
            </div>
        </MainLayout>
    );
}