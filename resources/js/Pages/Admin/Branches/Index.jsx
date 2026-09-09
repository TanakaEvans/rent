import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function BranchesIndex({ auth, branches }) {
    const handleDelete = (branch) => {
        if (confirm(`Are you sure you want to delete "${branch.name}"?`)) {
            router.delete(route('admin.branches.destroy', branch.id));
        }
    };

    const handleToggleStatus = (branch) => {
        router.patch(route('admin.branches.toggle-status', branch.id));
    };

    return (
        <AdminLayout title="Branches" auth={auth}>
            <Head title="Branches" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">Branches</h2>
                        <p className="text-gray-600">Manage company branches and locations</p>
                    </div>
                    <Link
                        href={route('admin.branches.create')}
                        className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors flex items-center gap-2"
                    >
                        <span>+</span>
                        Add Branch
                    </Link>
                </div>

                {/* Branches Table */}
                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Branch
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Code
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Contact
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Employees
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {branches.data.length > 0 ? (
                                branches.data.map((branch) => (
                                    <tr key={branch.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                                    <span className="text-orange-600">🏠</span>
                                                </div>
                                                <div className="ml-4">
                                                    <div className="text-sm font-medium text-gray-900 flex items-center gap-2">
                                                        {branch.name}
                                                        {branch.is_main_branch && (
                                                            <span className="px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                                Main
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-sm text-gray-500">{branch.city}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="px-2 py-1 text-xs font-mono bg-gray-100 rounded">
                                                {branch.code}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900">{branch.phone || '-'}</div>
                                            <div className="text-sm text-gray-500">{branch.email || '-'}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="text-sm text-gray-900">{branch.employees_count || 0}</span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <button
                                                onClick={() => handleToggleStatus(branch)}
                                                className={`px-3 py-1 text-xs font-semibold rounded-full ${branch.status === 'active'
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800'
                                                    }`}
                                            >
                                                {branch.status}
                                            </button>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end gap-2">
                                                <Link
                                                    href={route('admin.branches.show', branch.id)}
                                                    className="text-gray-600 hover:text-gray-900"
                                                >
                                                    View
                                                </Link>
                                                <Link
                                                    href={route('admin.branches.edit', branch.id)}
                                                    className="text-orange-600 hover:text-orange-900"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    onClick={() => handleDelete(branch)}
                                                    className="text-red-600 hover:text-red-900"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                        No branches found. Create your first branch to get started.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {branches.links && branches.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                            <div className="text-sm text-gray-500">
                                Showing {branches.from} to {branches.to} of {branches.total} results
                            </div>
                            <div className="flex gap-2">
                                {branches.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        preserveState
                                        preserveScroll
                                        className={`px-3 py-1 text-sm rounded ${link.active
                                                ? 'bg-orange-600 text-white'
                                                : link.url
                                                    ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                                    : 'bg-gray-50 text-gray-400 cursor-not-allowed'
                                            }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
