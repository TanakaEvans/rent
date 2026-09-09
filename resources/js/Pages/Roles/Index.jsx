import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RolesIndex({ auth, roles, filters }) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('auth.roles.index'), {
            search,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setSearch('');
        router.get(route('auth.roles.index'));
    };

    const deleteRole = (role) => {
        if (confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
            router.delete(route('auth.roles.destroy', role.id));
        }
    };

    return (
        <AdminLayout title="Role Management" auth={auth}>
            <Head title="Role Management" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Role Management</h1>
                        <p className="text-gray-600">Manage system roles and permissions</p>
                    </div>
                    <Link
                        href={route('auth.roles.create')}
                        className="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
                    >
                        <span>🛡️</span>
                        Create New Role
                    </Link>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl shadow-sm p-6">
                    <form onSubmit={handleSearch} className="flex flex-wrap gap-4 items-end">
                        <div className="flex-1 min-w-64">
                            <label htmlFor="search" className="block text-sm font-medium text-gray-700 mb-1">
                                Search Roles
                            </label>
                            <input
                                id="search"
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by name or description..."
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                            />
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors"
                            >
                                Search
                            </button>
                            {(search) && (
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors"
                                >
                                    Clear
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                {/* Roles List */}
                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-200">
                        <h3 className="text-lg font-semibold text-gray-900">
                            System Roles ({roles.total} total)
                        </h3>
                    </div>

                    {roles.data.length > 0 ? (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="text-left py-3 px-6 font-medium text-gray-900">Role Name</th>
                                            <th className="text-left py-3 px-6 font-medium text-gray-900">Description</th>
                                            <th className="text-left py-3 px-6 font-medium text-gray-900">Users</th>
                                            <th className="text-left py-3 px-6 font-medium text-gray-900">Permissions</th>
                                            <th className="text-left py-3 px-6 font-medium text-gray-900">Created</th>
                                            <th className="text-right py-3 px-6 font-medium text-gray-900">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {roles.data.map((role) => (
                                            <tr key={role.id} className="hover:bg-gray-50">
                                                <td className="py-4 px-6">
                                                    <div className="font-medium text-gray-900">{role.name}</div>
                                                </td>
                                                <td className="py-4 px-6">
                                                    <div className="text-gray-600">{role.description || 'No description'}</div>
                                                </td>
                                                <td className="py-4 px-6">
                                                    <span className="px-2 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                                        {role.users_count}
                                                    </span>
                                                </td>
                                                <td className="py-4 px-6">
                                                    <span className="px-2 py-1 bg-purple-100 text-purple-800 text-sm rounded-full">
                                                        {role.system_routes_count || 0}
                                                    </span>
                                                </td>
                                                <td className="py-4 px-6 text-gray-600">
                                                    {new Date(role.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="py-4 px-6 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Link
                                                            href={route('auth.roles.show', role.id)}
                                                            className="text-blue-600 hover:text-blue-800 px-2 py-1 rounded transition-colors"
                                                            title="View Details"
                                                        >
                                                            👁️
                                                        </Link>
                                                        <Link
                                                            href={route('auth.roles.edit', role.id)}
                                                            className="text-green-600 hover:text-green-800 px-2 py-1 rounded transition-colors"
                                                            title="Edit Role"
                                                        >
                                                            ✏️
                                                        </Link>
                                                        <button
                                                            onClick={() => deleteRole(role)}
                                                            className="text-red-600 hover:text-red-800 px-2 py-1 rounded transition-colors"
                                                            title="Delete Role"
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
                            {roles.links && (
                                <div className="px-6 py-4 border-t border-gray-200">
                                    <div className="flex items-center justify-between">
                                        <div className="text-sm text-gray-700">
                                            Showing {roles.from} to {roles.to} of {roles.total} results
                                        </div>
                                        <div className="flex gap-2">
                                            {roles.links.map((link, index) => (
                                                <Link
                                                    key={index}
                                                    href={link.url || ''}
                                                    preserveState
                                                    preserveScroll
                                                    className={`px-3 py-2 text-sm rounded-lg transition-colors ${link.active
                                                            ? 'bg-orange-600 text-white'
                                                            : link.url
                                                                ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                        }`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-center py-12">
                            <div className="text-gray-500 text-lg mb-2">No roles found</div>
                            <div className="text-gray-400">Try adjusting your search criteria</div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
