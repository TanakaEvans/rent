import { Head, Link } from '@inertiajs/react';
import { Flag, MapPin, ShieldAlert, Clock } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import EmptyState from '@/Components/Shared/EmptyState';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

const reportCategoryLabels = {
    suspicious_listing: 'Suspicious listing',
    incorrect_information: 'Incorrect information',
    duplicate: 'Duplicate listing',
    wrong_price: 'Wrong price',
    fraud_concern: 'Fraud concern',
    already_rented: 'Already rented out',
    inappropriate_content: 'Inappropriate content',
};

export default function TenantReports({ reports = [] }) {
    return (
        <MainLayout title="My Reports">
            <Head title="My Reports" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Reports & Feedback</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Everything you have flagged for review — our team investigates each one.
                    </p>
                </div>
                <Link href={route('home')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    Back to marketplace
                </Link>
            </div>

            {reports.length === 0 ? (
                <EmptyState
                    icon={Flag}
                    title="No reports yet"
                    description="If you spot a listing that looks wrong or suspicious, open it and use “Report listing”. It lands here so you can track the outcome."
                />
            ) : (
                <div className="space-y-4">
                    {reports.map((report) => (
                        <article key={report.id} className="surface p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-100 text-rose-600">
                                        <ShieldAlert className="h-5 w-5" />
                                    </span>
                                    <div>
                                        <h3 className="text-sm font-extrabold text-foreground">
                                            {reportCategoryLabels[report.category] || report.category.replace(/_/g, ' ')}
                                        </h3>
                                        {report.subject_type === 'property' && (
                                            <Link
                                                href={route('property.show', report.subject_id)}
                                                className="mt-0.5 inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                                            >
                                                <MapPin className="h-3 w-3" /> View the listing
                                            </Link>
                                        )}
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <StatusBadge status={report.priority} />
                                    <StatusBadge status={report.status} />
                                </div>
                            </div>

                            <p className="mt-3 rounded-xl bg-muted/60 px-4 py-3 text-sm leading-6 text-muted-foreground">
                                {report.description}
                            </p>

                            <p className="mt-3 flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                                <Clock className="h-3.5 w-3.5" />
                                Reported {new Date(report.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                                {report.resolved_at && ` · resolved ${new Date(report.resolved_at).toLocaleDateString()}`}
                            </p>

                            {report.resolution_note && (
                                <p className="mt-3 border-l-2 border-emerald-300 bg-emerald-50/70 px-4 py-3 text-sm leading-6 text-emerald-900">
                                    {report.resolution_note}
                                </p>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </MainLayout>
    );
}