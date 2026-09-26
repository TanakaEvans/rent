import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const inputClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500';

function Field({ id, label, error, children }) {
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-gray-700">
                {label}
            </label>
            {children}
            {error && <div className="mt-2 text-sm text-red-600">{error}</div>}
        </div>
    );
}

export default function Edit({ user, roles }) {
    const { auth } = usePage().props;
    const actorIsSuperuser = (auth?.user?.roles || []).some((r) => r.name === 'Superuser');

    const { data, setData, patch, processing, errors } = useForm({
        name: user.name || '',
        email: user.email || '',
        username: user.username || '',
        status: user.status || 'active',
        password: '',
        password_confirmation: '',
        roles: (user.roles || []).map((role) => role.id),
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('auth.users.update', user.id));
    };

    const toggleRole = (roleId) => {
        setData('roles', data.roles.includes(roleId) ? data.roles.filter((id) => id !== roleId) : [...data.roles, roleId]);
    };

    return (
        <AdminLayout title="Edit User">
            <Head title={`Edit User - ${user.name}`} />

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900">
                    <div className="flex justify-between items-center mb-6">
                        <h1 className="text-2xl font-semibold">Edit User: {user.name}</h1>
                        <Link href={route('auth.users.index')} className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Back to Users
                        </Link>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <Field id="name" label="Full name" error={errors.name}>
                                <input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputClass} required />
                            </Field>
                            <Field id="email" label="Email" error={errors.email}>
                                <input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={inputClass} required />
                            </Field>
                            <Field id="username" label="Username" error={errors.username}>
                                <input id="username" type="text" value={data.username} onChange={(e) => setData('username', e.target.value)} className={inputClass} required />
                            </Field>
                            <Field id="status" label="Account status" error={errors.status}>
                                <select id="status" value={data.status} onChange={(e) => setData('status', e.target.value)} className={inputClass}>
                                    <option value="active">Active (can sign in)</option>
                                    <option value="inactive">Inactive (cannot sign in)</option>
                                </select>
                            </Field>
                            <Field id="password" label="New password (leave blank to keep current)" error={errors.password}>
                                <input id="password" type="password" autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} className={inputClass} />
                            </Field>
                            <Field id="password_confirmation" label="Confirm new password" error={errors.password_confirmation}>
                                <input id="password_confirmation" type="password" autoComplete="new-password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className={inputClass} />
                            </Field>
                        </div>
                        {data.password && (
                            <p className="text-xs text-gray-500">The user will be asked to choose their own password the next time they sign in.</p>
                        )}

                        <fieldset>
                            <legend className="block text-sm font-medium text-gray-700 mb-3">Roles</legend>
                            <div className="space-y-2">
                                {roles.map((role) => {
                                    const locked = role.name === 'Superuser' && !actorIsSuperuser;
                                    return (
                                        <label key={role.id} className={`flex items-center ${locked ? 'opacity-60' : ''}`}>
                                            <input
                                                type="checkbox"
                                                checked={data.roles.includes(role.id)}
                                                onChange={() => toggleRole(role.id)}
                                                disabled={locked}
                                                className="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">
                                                {role.name}
                                                {role.description && <span className="text-gray-500"> - {role.description}</span>}
                                                {locked && <span className="text-gray-500"> (only a Superuser can change this)</span>}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            {errors.roles && <div className="mt-2 text-sm text-red-600">{errors.roles}</div>}
                        </fieldset>

                        <div className="flex items-center justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Save changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
