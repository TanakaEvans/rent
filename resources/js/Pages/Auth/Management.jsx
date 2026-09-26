import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const statusBadge = (user) => {
    if (user.locked_at) {
        return { label: 'Locked (too many attempts)', classes: 'bg-amber-100 text-amber-800' };
    }
    return user.status === 'active'
        ? { label: 'Active', classes: 'bg-green-100 text-green-700' }
        : { label: 'Deactivated', classes: 'bg-red-100 text-red-700' };
};

export default function AuthManagement({ users, filters = {} }) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('auth.management'), search ? { search } : {}, { preserveState: true, preserveScroll: true });
    };

    const clearSearch = () => {
        setSearch('');
        router.get(route('auth.management'));
    };

    const handleReset = (user) => {
        const how = user.employee?.last_name
            ? "Their temporary password will be their surname in lowercase."
            : 'A random temporary password will be generated and shown to you once.';
        if (confirm(`Reset the password for ${user.name}? ${how} They will have to choose a new password at their next sign-in.`)) {
            router.post(route('auth.management.reset', user.id), {}, { preserveScroll: true });
        }
    };

    const handleToggleStatus = (user) => {
        const action = user.status === 'active' ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} ${user.name}?`)) {
            router.patch(route('auth.management.toggle-status', user.id), {}, { preserveScroll: true });
        }
    };

    const handleUnlock = (user) => {
        if (confirm(`Unlock ${user.name}? Their failed sign-in count will be cleared.`)) {
            router.post(route('auth.management.unlock', user.id), {}, { preserveScroll: true });
        }
    };

    return (
        <AdminLayout title="Auth Management">
            <Head title="Auth Management" />

            <div className="space-y-6">
                {flash?.success && (
                    <div role="status" className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
                        {flash.error}
                    </div>
                )}

                <div className="bg-white rounded-xl shadow-sm p-6">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">User Authentication Management</h1>
                            <p className="text-gray-600">Reset passwords, unlock locked accounts, and activate or deactivate sign-in.</p>
                        </div>
                        <form onSubmit={submitSearch} className="flex w-full md:w-auto gap-2">
                            <input
                                type="text"
                                aria-label="Search users"
                                placeholder="Search name, email or username..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full md:w-72 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                            <button type="submit" className="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">
                                Search
                            </button>
                            {filters.search && (
                                <button type="button" onClick={clearSearch} className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">
                                    Clear
                                </button>
                            )}
                        </form>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-gray-50 text-gray-600 text-sm">
                                    <th className="px-4 py-3 rounded-l-lg">User</th>
                                    <th className="px-4 py-3">Roles</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 rounded-r-lg text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan="4" className="px-4 py-8 text-center text-sm text-gray-500">No users found.</td>
                                    </tr>
                                )}
                                {users.data.map((user) => {
                                    const badge = statusBadge(user);
                                    return (
                                        <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center font-semibold text-sm">
                                                        {user.name.charAt(0)}
                                                    </div>
                                                    <div>
                                                        <div className="font-medium text-gray-900">{user.name}</div>
                                                        <div className="text-xs text-gray-500">{user.email} · {user.username}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles.map((role) => (
                                                        <span key={role.id} className="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-600">
                                                            {role.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`px-2 py-1 rounded-full text-xs font-medium ${badge.classes}`}>{badge.label}</span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {user.locked_at && (
                                                        <button
                                                            onClick={() => handleUnlock(user)}
                                                            className="px-3 py-1.5 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-colors"
                                                        >
                                                            Unlock
                                                        </button>
                                                    )}
                                                    <button
                                                        onClick={() => handleReset(user)}
                                                        className="px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                                                    >
                                                        Reset password
                                                    </button>
                                                    <button
                                                        onClick={() => handleToggleStatus(user)}
                                                        className={`px-3 py-1.5 text-xs font-medium text-white rounded-lg transition-colors ${user.status === 'active'
                                                            ? 'bg-orange-500 hover:bg-orange-600'
                                                            : 'bg-green-600 hover:bg-green-700'
                                                            }`}
                                                    >
                                                        {user.status === 'active' ? 'Deactivate' : 'Activate'}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {users.last_page > 1 && (
                        <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-gray-600">
                                Showing {users.from}–{users.to} of {users.total} users
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {users.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            preserveScroll
                                            className={`px-3 py-1.5 text-sm rounded-lg border ${link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            className="px-3 py-1.5 text-sm rounded-lg border border-gray-100 text-gray-400"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
