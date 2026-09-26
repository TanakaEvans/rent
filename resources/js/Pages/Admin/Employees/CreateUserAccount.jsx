import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const inputClass = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent';

export default function CreateUserAccount({ employee, roles }) {
    const { auth } = usePage().props;
    const actorIsSuperuser = (auth?.user?.roles || []).some((r) => r.name === 'Superuser');

    const { data, setData, post, processing, errors } = useForm({
        email: employee.email || '',
        username: employee.employee_number || '',
        password: '',
        role_ids: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.employees.store-user', employee.id));
    };

    const toggleRole = (roleId) => {
        setData('role_ids', data.role_ids.includes(roleId) ? data.role_ids.filter((id) => id !== roleId) : [...data.role_ids, roleId]);
    };

    return (
        <AdminLayout title="Create User Account">
            <Head title={`Create User Account - ${employee.first_name} ${employee.last_name}`} />

            <div className="max-w-3xl mx-auto">
                <div className="mb-6">
                    <Link href={route('admin.employees.show', employee.id)} className="text-orange-600 hover:text-orange-700 flex items-center gap-1 mb-2">
                        ← Back to Employee
                    </Link>
                    <h2 className="text-2xl font-bold text-gray-900">Create User Account</h2>
                    <p className="text-gray-600">
                        Give {employee.first_name} {employee.last_name} a sign-in for the admin portal.
                    </p>
                </div>

                <form onSubmit={submit} className="bg-white rounded-xl shadow-sm p-6 space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <label className="block">
                            <span className="block text-sm font-medium text-gray-700 mb-1">Email *</span>
                            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={inputClass} required />
                            <span className="mt-1 block text-xs text-gray-500">Also saved on the employee record.</span>
                            {errors.email && <span className="mt-1 block text-sm text-red-600">{errors.email}</span>}
                        </label>
                        <label className="block">
                            <span className="block text-sm font-medium text-gray-700 mb-1">Username *</span>
                            <input type="text" value={data.username} onChange={(e) => setData('username', e.target.value)} className={`${inputClass} font-mono`} required />
                            {errors.username && <span className="mt-1 block text-sm text-red-600">{errors.username}</span>}
                        </label>
                        <label className="block md:col-span-2">
                            <span className="block text-sm font-medium text-gray-700 mb-1">Temporary Password *</span>
                            <input type="password" autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} className={inputClass} minLength={8} required />
                            <span className="mt-1 block text-xs text-gray-500">At least 8 characters. The employee must choose their own password at first sign-in.</span>
                            {errors.password && <span className="mt-1 block text-sm text-red-600">{errors.password}</span>}
                        </label>
                    </div>

                    <fieldset>
                        <legend className="block text-sm font-medium text-gray-700 mb-3">Assign Roles</legend>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                            {roles.map((role) => {
                                const locked = role.name === 'Superuser' && !actorIsSuperuser;
                                return (
                                    <label
                                        key={role.id}
                                        className={`flex items-center gap-3 p-3 border rounded-lg ${locked ? 'opacity-50' : 'cursor-pointer'} ${data.role_ids.includes(role.id) ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:bg-gray-50'}`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.role_ids.includes(role.id)}
                                            onChange={() => toggleRole(role.id)}
                                            disabled={locked}
                                            className="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500"
                                        />
                                        <span className="text-sm font-medium text-gray-700">{role.name}</span>
                                    </label>
                                );
                            })}
                        </div>
                        {errors.role_ids && <p className="mt-2 text-sm text-red-600">{errors.role_ids}</p>}
                    </fieldset>

                    <div className="flex justify-end gap-3">
                        <Link href={route('admin.employees.show', employee.id)} className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </Link>
                        <button type="submit" disabled={processing} className="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50">
                            {processing ? 'Creating...' : 'Create User Account'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
