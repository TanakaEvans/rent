import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function UsersIndex({ auth, users, roles, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [role, setRole] = useState(filters.role || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('auth.users.index'), {
            search,
            status,
            role,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        setRole('');
        router.get(route('auth.users.index'));
    };

    const toggleUserStatus = (user) => {
        if (confirm(`Are you sure you want to ${user.status === 'active' ? 'deactivate' : 'activate'} this user?`)) {
            router.patch(route('auth.users.toggle-status', user.id), {}, {
                preserveScroll: true,
            });
        }
    };

    const deleteUser = (user) => {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            router.delete(route('auth.users.destroy', user.id));
        }
    };

    return (
        <AdminLayout title="User Management" auth={auth}>
            <Head title="User Management" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">System Users</h1>
                        <p className="text-gray-600">View and manage user accounts linked to employees</p>
                    </div>
                    <Link
                        href={route('admin.employees.create')}
                        className="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
                    >
                        <span>👔</span>
                        Add Employee with User Account
                    </Link>
                </div>

                {/* Info Banner */}
                <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
                    <span className="text-2xl">ℹ️</span>
                    <div>
                        <h3 className="font-semibold text-blue-900">How User Accounts Work</h3>
                        <p className="text-blue-800 text-sm mt-1">
                            User accounts are created when adding or editing an <strong>Employee</strong>.
                            Go to <Link href={route('admin.employees.index')} className="underline font-medium">Employees</Link> to
                            create new staff members and optionally give them login access to the system.
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl shadow-sm p-6">
                    <form onSubmit={handleSearch} className="flex flex-wrap gap-4 items-end">
                        <div className="flex-1 min-w-64">
                            <label htmlFor="search" className="block text-sm font-medium text-gray-700 mb-1">
                                Search Users
                            </label>
                            <input
                                id="search"
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by name, email, or username..."
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div className="min-w-40">
                            <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-1">
                                Status
                            </label>
                            <select
                                id="status"
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div className="min-w-40">
                            <label htmlFor="role" className="block text-sm font-medium text-gray-700 mb-1">
                                Role
                            </label>
                            <select
                                id="role"
                                value={role}
                                onChange={(e) => setRole(e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">All Roles</option>
                                {roles.map((r) => (
                                    <option key={r.id} value={r.name}>{r.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors"
                            >
                                Search
                            </button>
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors"
                            >
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                {/* Users Table */}
                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-gray-50 border-b">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        User
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Contact
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Roles
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Created
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <div className="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                                    {user.name.charAt(0)}
                                                </div>
                                                <div className="ml-4">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {user.name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        @{user.username}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900">{user.email}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.map((role) => (
                                                    <span key={role.id} className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                        {role.name}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-1 text-xs rounded-full ${user.status === 'active'
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                                }`}>
                                                {user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {new Date(user.created_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link
                                                    href={route('auth.users.show', user.id)}
                                                    className="text-blue-600 hover:text-blue-900 p-1"
                                                    title="View User"
                                                >
                                                    👁️
                                                </Link>
                                                <Link
                                                    href={route('auth.users.edit', user.id)}
                                                    className="text-indigo-600 hover:text-indigo-900 p-1"
                                                    title="Edit User"
                                                >
                                                    ✏️
                                                </Link>
                                                <button
                                                    onClick={() => toggleUserStatus(user)}
                                                    className={`p-1 ${user.status === 'active'
                                                            ? 'text-yellow-600 hover:text-yellow-900'
                                                            : 'text-green-600 hover:text-green-900'
                                                        }`}
                                                    title={user.status === 'active' ? 'Deactivate User' : 'Activate User'}
                                                >
                                                    {user.status === 'active' ? '⏸️' : '▶️'}
                                                </button>
                                                <button
                                                    onClick={() => deleteUser(user)}
                                                    className="text-red-600 hover:text-red-900 p-1"
                                                    title="Delete User"
                                                >
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {users.links && (
                        <div className="bg-gray-50 px-6 py-3 flex items-center justify-between border-t">
                            <div className="flex-1 flex justify-between sm:hidden">
                                {users.prev_page_url && (
                                    <Link
                                        href={users.prev_page_url}
                                        className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Previous
                                    </Link>
                                )}
                                {users.next_page_url && (
                                    <Link
                                        href={users.next_page_url}
                                        className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Next
                                    </Link>
                                )}
                            </div>
                            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm text-gray-700">
                                        Showing <span className="font-medium">{users.from}</span> to <span className="font-medium">{users.to}</span> of{' '}
                                        <span className="font-medium">{users.total}</span> results
                                    </p>
                                </div>
                                <div>
                                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        {users.links.map((link, index) => (
                                            <Link
                                                key={index}
                                                href={link.url}
                                                preserveState
                                                preserveScroll
                                                className={`relative inline-flex items-center px-2 py-2 border text-sm font-medium ${link.active
                                                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                                    } ${index === 0 ? 'rounded-l-md' : ''} ${index === users.links.length - 1 ? 'rounded-r-md' : ''
                                                    }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </nav>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {users.data.length === 0 && (
                    <div className="bg-white rounded-xl shadow-sm p-12 text-center">
                        <div className="text-6xl mb-4">👤</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                        <p className="text-gray-500 mb-6">
                            {filters.search || filters.status || filters.role
                                ? 'Try adjusting your search criteria'
                                : 'Get started by creating your first user'
                            }
                        </p>
                        <Link
                            href={route('auth.users.create')}
                            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2 transition-colors"
                        >
                            <span>➕</span>
                            Add New User
                        </Link>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
