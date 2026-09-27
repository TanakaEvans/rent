import { useRef, useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { IdCard, Trash2, Download, Upload, ShieldCheck, UserRound, ImagePlus } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import Avatar from '@/Components/Shared/Avatar';
import { Button } from '@/Components/ui/button';

const fmt = (value) => (value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
const bytes = (value) => `${(Number(value || 0) / 1024).toFixed(1)} KB`;

const badgeMeta = {
    gold: { pill: 'border-amber-200 bg-amber-50 text-amber-700', label: 'Gold badge', title: 'Both documents approved' },
    silver: { pill: 'border-slate-200 bg-slate-50 text-slate-600', label: 'Silver badge', title: 'One document approved' },
    bronze: { pill: 'border-orange-200 bg-orange-50 text-orange-700', label: 'Bronze badge', title: 'Evidence submitted, awaiting review' },
    none: { pill: 'border-border bg-muted/40 text-muted-foreground', label: 'No badge yet', title: 'Upload identity evidence to get verified' },
};

const tierMeta = {
    full: 'Full — both documents approved',
    basic: 'Basic — one approved document',
    none: 'None — upload evidence to start',
};

function KycCard({ type, label, document }) {
    const inputRef = useRef(null);
    const [file, setFile] = useState(null);
    const form = useForm({ type, document: null });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('tenant.profile.documents.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setFile(null);
                if (inputRef.current) inputRef.current.value = '';
            },
        });
    };

    const onPick = (e) => {
        const picked = e.target.files?.[0] || null;
        setFile(picked);
        form.setData('document', picked);
    };

    const remove = () => {
        if (window.confirm(`Delete your ${label.toLowerCase()} scan? The audit trail is kept.`)) {
            router.delete(route('tenant.profile.documents.destroy', document.id), { preserveScroll: true });
        }
    };

    return (
        <div className="surface flex flex-col gap-3 p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                        <IdCard className="h-5 w-5" />
                    </span>
                    <div>
                        <h4 className="text-sm font-extrabold tracking-tight text-foreground">{label}</h4>
                        {document ? (
                            <p className="mt-0.5 max-w-56 truncate text-xs text-muted-foreground">
                                {document.original_name} · {bytes(document.size)} · {fmt(document.created_at)}
                            </p>
                        ) : (
                            <p className="mt-0.5 text-xs text-muted-foreground">Not uploaded</p>
                        )}
                    </div>
                </div>
                {document && <StatusBadge status={document.status} />}
            </div>

            <form onSubmit={submit} className="flex flex-col gap-3">
                {document && (
                    <div className="flex flex-wrap gap-2 border-t border-border pt-3">
                        <Button size="sm" variant="outline" asChild>
                            <a href={route('tenant.profile.documents.download', document.id)}>
                                <Download className="h-4 w-4" /> Download
                            </a>
                        </Button>
                        <Button size="sm" variant="outline" type="button" onClick={() => inputRef.current?.click()} disabled={form.processing}>
                            <Upload className="h-4 w-4" /> Replace
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            className="text-muted-foreground hover:bg-rose-50 hover:text-rose-500"
                            onClick={remove}
                        >
                            <Trash2 className="h-4 w-4" /> Remove
                        </Button>
                    </div>
                )}

                {!document && (
                    <div className="flex flex-wrap items-center gap-2 border-t border-border pt-3">
                        <Button size="sm" type="button" onClick={() => inputRef.current?.click()} disabled={form.processing}>
                            <Upload className="h-4 w-4" /> {form.processing ? 'Uploading…' : 'Upload scan'}
                        </Button>
                        <span className="text-xs text-muted-foreground">JPG, PNG or PDF · up to 4 MB</span>
                    </div>
                )}

                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,application/pdf"
                    className="hidden"
                    onChange={onPick}
                />

                {file && (
                    <div className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted px-3 py-2">
                        <span className="truncate text-xs font-semibold text-foreground">{file.name}</span>
                        <Button type="submit" size="sm" disabled={form.processing}>
                            <ShieldCheck className="h-4 w-4" /> {form.processing ? 'Uploading…' : 'Confirm upload'}
                        </Button>
                    </div>
                )}
                {form.errors.document && <p className="text-xs font-semibold text-rose-600">{form.errors.document}</p>}
                {form.errors.type && <p className="text-xs font-semibold text-rose-600">{form.errors.type}</p>}
            </form>
        </div>
    );
}

function AvatarBlock() {
    const { auth } = usePage().props;
    const user = auth?.user || {};
    const inputRef = useRef(null);
    const [error, setError] = useState(null);
    const form = useForm({ avatar: null });

    const onPick = (e) => {
        const picked = e.target.files?.[0] || null;
        e.target.value = '';
        if (!picked) return;
        setError(null);
        form.setData('avatar', picked);
        form.post(route('account.avatar.store'), {
            preserveScroll: true,
            forceFormData: true,
            onError: (errors) => setError(errors.avatar || 'Upload failed.'),
            onSuccess: () => form.setData('avatar', null),
        });
    };

    const remove = () => {
        if (window.confirm('Remove your profile photo?')) {
            router.delete(route('account.avatar.destroy'), { preserveScroll: true });
        }
    };

    return (
        <div className="surface mb-6 flex flex-col gap-4 p-6 sm:flex-row sm:items-center">
            <Avatar user={user} size={72} className="ring-4 ring-primary/10" />
            <div className="flex-1">
                <h3 className="text-base font-extrabold tracking-tight">Profile photo</h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Shown across your signed-in dashboard. JPG, PNG or WebP · up to 4 MB.
                </p>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <Button size="sm" type="button" onClick={() => inputRef.current?.click()} disabled={form.processing}>
                        {user?.avatar_url ? <Upload className="h-4 w-4" /> : <ImagePlus className="h-4 w-4" />}
                        {form.processing ? 'Uploading…' : user?.avatar_url ? 'Change photo' : 'Upload photo'}
                    </Button>
                    {user?.avatar_url && (
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            className="text-muted-foreground hover:bg-rose-50 hover:text-rose-500"
                            onClick={remove}
                        >
                            <Trash2 className="h-4 w-4" /> Remove
                        </Button>
                    )}
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={onPick}
                    />
                </div>
                {error && <p className="mt-2 text-xs font-semibold text-rose-600">{error}</p>}
            </div>
        </div>
    );
}

export default function TenantProfilePage({ profile = null, documents = [], kyc_tier = 'none', badge_tier = 'none', options = {} }) {
    const employmentOptions = options.employment_status || [];
    const salaryOptions = options.salary_band || [];
    const contactOptions = options.preferred_contact || [];
    const kycTypes = options.kyc_types || [];

    const form = useForm({
        phone: profile?.phone || '',
        city: profile?.city || '',
        employment_status: profile?.employment_status || '',
        salary_band: profile?.salary_band || '',
        preferred_contact: profile?.preferred_contact || '',
        about: profile?.about || '',
    });

    const docsByType = Object.fromEntries((documents || []).map((d) => [d.type, d]));

    return (
        <MainLayout title="My Profile">
            <Head title="My Profile" />

            <div className="mb-7">
                <h2 className="flex items-center gap-2.5 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    <UserRound className="h-7 w-7 text-primary" /> My Profile
                </h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Tell owners who they'd be renting to — and verify your identity so your applications carry real trust.
                </p>
            </div>

            <AvatarBlock />

            <div className="grid gap-6 lg:grid-cols-5">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('tenant.profile.update'), { preserveScroll: true });
                    }}
                    className="surface space-y-4 p-6 lg:col-span-3"
                >
                    <h3 className="text-base font-extrabold tracking-tight">Profile details</h3>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Phone</label>
                            <input
                                type="tel"
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                                placeholder="+263 7xx xxx xxx"
                                maxLength={30}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            {form.errors.phone && <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.phone}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">City</label>
                            <input
                                type="text"
                                value={form.data.city}
                                onChange={(e) => form.setData('city', e.target.value)}
                                placeholder="Harare"
                                maxLength={100}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            {form.errors.city && <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.city}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Employment</label>
                            <select
                                value={form.data.employment_status}
                                onChange={(e) => form.setData('employment_status', e.target.value)}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="">Prefer not to say</option>
                                {employmentOptions.map((o) => (
                                    <option key={o.key} value={o.key}>{o.label}</option>
                                ))}
                            </select>
                            {form.errors.employment_status && <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.employment_status}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Monthly income band</label>
                            <select
                                value={form.data.salary_band}
                                onChange={(e) => form.setData('salary_band', e.target.value)}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="">Prefer not to say</option>
                                {salaryOptions.map((o) => (
                                    <option key={o.key} value={o.key}>{o.label}</option>
                                ))}
                            </select>
                            {form.errors.salary_band && <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.salary_band}</p>}
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Preferred contact</label>
                            <select
                                value={form.data.preferred_contact}
                                onChange={(e) => form.setData('preferred_contact', e.target.value)}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="">Let owners decide</option>
                                {contactOptions.map((o) => (
                                    <option key={o.key} value={o.key}>{o.label}</option>
                                ))}
                            </select>
                            {form.errors.preferred_contact && <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.preferred_contact}</p>}
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">About you</label>
                            <textarea
                                value={form.data.about}
                                onChange={(e) => form.setData('about', e.target.value)}
                                rows={4}
                                maxLength={1000}
                                placeholder="Quiet professional, references available…"
                                className="w-full resize-y rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            {form.errors.about && <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.about}</p>}
                        </div>
                    </div>

                    <div className="pt-1">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save profile'}
                        </Button>
                    </div>
                </form>

                <section className="space-y-4 lg:col-span-2">
                    <div className="flex items-center gap-2">
                        <ShieldCheck className="h-5 w-5 text-emerald-600" />
                        <h3 className="text-base font-extrabold tracking-tight">Identity verification (KYC)</h3>
                    </div>

                    <div className={`flex items-center justify-between gap-3 rounded-xl border p-3.5 shadow-sm ${badgeMeta[badge_tier]?.pill || badgeMeta.none.pill}`} title={badgeMeta[badge_tier]?.title}>
                        <div>
                            <p className="text-xs font-bold uppercase tracking-wider text-current">{badgeMeta[badge_tier]?.label}</p>
                            <p className="mt-0.5 text-xs font-medium text-current/80">{tierMeta[kyc_tier]}</p>
                        </div>
                        <ShieldCheck className="h-5 w-5 shrink-0 text-current" />
                    </div>

                    <p className="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-medium leading-relaxed text-emerald-800">
                        Your scans are stored privately — never shown publicly. Only you and ZimRent staff can
                        access them, and every upload or removal is recorded in an audit trail.
                    </p>

                    {kycTypes.map((t) => (
                        <KycCard key={t.key} type={t.key} label={t.label} document={docsByType[t.key]} />
                    ))}
                </section>
            </div>
        </MainLayout>
    );
}