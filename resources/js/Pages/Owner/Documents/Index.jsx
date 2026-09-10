import { Head, Link } from '@inertiajs/react';
import { FolderOpen, FileText, Download, MapPin, ShieldCheck } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import EmptyState from '@/Components/Shared/EmptyState';

const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
const bytes = (value) => `${(Number(value || 0) / 1024).toFixed(1)} KB`;

export default function OwnerDocumentsIndex({ documents = [] }) {
    return (
        <MainLayout title="Documents">
            <Head title="Documents" />

            <div className="mb-7">
                <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Agreement Documents</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Stored residential snapshots of every signed lease on your properties — view or download anytime.
                </p>
            </div>

            {documents.length === 0 ? (
                <EmptyState
                    icon={FolderOpen}
                    title="No documents yet"
                    description="Once a lease is signed by both parties, its agreement is stored here automatically."
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {documents.map((document) => (
                        <article key={document.id} className="surface flex flex-col p-5">
                            <div className="mb-4 flex items-start gap-3">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                    <FileText className="h-5 w-5" />
                                </span>
                                <div className="min-w-0">
                                    <h3 className="truncate font-extrabold text-foreground">{document.name}</h3>
                                    <p className="font-mono text-xs font-bold text-muted-foreground">{document.lease?.lease_no || '—'}</p>
                                </div>
                            </div>

                            <div className="mb-4 space-y-2 text-sm">
                                <p className="flex items-center gap-2 truncate font-semibold text-foreground">
                                    {document.lease?.property?.title || 'Property'}
                                </p>
                                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <MapPin className="h-3.5 w-3.5 text-emerald-500" />
                                    {[document.lease?.property?.suburb, document.lease?.property?.city].filter(Boolean).join(', ') || 'Location on request'}
                                </p>
                                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />
                                    Stored {fmt(document.created_at)} · version {document.version} · {bytes(document.size)}
                                </p>
                            </div>

                            <div className="mt-auto flex items-center gap-2">
                                <Link
                                    href={route('documents.show', document.id)}
                                    className="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary text-sm font-bold text-primary-foreground transition hover:bg-primary/90"
                                >
                                    <FileText className="h-4 w-4" /> View
                                </Link>
                                <a
                                    href={route('documents.download', document.id)}
                                    className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-bold text-foreground transition hover:bg-muted"
                                >
                                    <Download className="h-4 w-4" /> Download
                                </a>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}