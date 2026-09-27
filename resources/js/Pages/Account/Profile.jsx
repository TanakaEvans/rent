import { useRef, useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { UserRound, Upload, Trash2, ImagePlus } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import AdminLayout from '@/Layouts/AdminLayout';
import Avatar from '@/Components/Shared/Avatar';
import { Button } from '@/Components/ui/button';

const ADMIN_ROLES = ['Admin', 'Superuser'];

function AvatarBlock({ user }) {
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
        <div className="surface flex flex-col gap-4 p-6 sm:flex-row sm:items-center">
            <Avatar user={user} size={80} className="ring-4 ring-primary/10" />
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

export default function AccountProfilePage() {
    const { auth } = usePage().props;
    const user = auth?.user || {};
    const roles = (user.roles || []).map((r) => r.name);
    const Layout = roles.some((r) => ADMIN_ROLES.includes(r)) ? AdminLayout : MainLayout;

    const form = useForm({ name: user.name || '' });

    return (
        <Layout title="Account settings">
            <Head title="Account settings" />

            <div className="mb-7">
                <h2 className="flex items-center gap-2.5 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    <UserRound className="h-7 w-7 text-primary" /> Account settings
                </h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Manage the name and profile photo shown across your ZimRent dashboard.
                </p>
            </div>

            <div className="grid max-w-3xl gap-6">
                <AvatarBlock user={user} />

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('account.profile.update'), { preserveScroll: true });
                    }}
                    className="surface space-y-4 p-6"
                >
                    <h3 className="text-base font-extrabold tracking-tight">Display name</h3>
                    <div>
                        <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Full name</label>
                        <input
                            type="text"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            maxLength={255}
                            className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                        {form.errors.name && <p className="mt-1 text-xs font-semibold text-rose-600">{form.errors.name}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted-foreground">Email</label>
                        <input
                            type="email"
                            value={user.email || ''}
                            disabled
                            className="w-full rounded-lg border border-input bg-muted px-3 py-2 text-sm text-muted-foreground"
                        />
                    </div>
                    <div className="pt-1">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save profile'}
                        </Button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
