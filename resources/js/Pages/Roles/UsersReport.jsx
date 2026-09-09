import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';

export default function UsersReport({ auth, users, roles }) {
    return (
        <AdminLayout title="Users with Roles Report" auth={auth}>
            <Head title="Users with Roles" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Users with Roles Report</h1>
                        <p className="text-gray-600">Overview of all system users and their assigned roles.</p>
                    </div>
                    <Link
                        href={route('auth.roles.index')}
                        className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors"
                    >
                        ← Back to Roles
                    </Link>
                </div>

                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead className="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4 font-semibold text-gray-900">User</th>
                                    <th className="px-6 py-4 font-semibold text-gray-900">Assigned Roles</th>
                                    <th className="px-6 py-4 font-semibold text-gray-900">Role Count</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-semibold text-sm">
                                                    {user.name.charAt(0)}
                                                </div>
                                                <div>
                                                    <div className="font-medium text-gray-900">{user.name}</div>
                                                    <div className="text-xs text-gray-500">{user.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                {user.roles && user.roles.length > 0 ? (
                                                    user.roles.map((role) => (
                                                        <span
                                                            key={role.id}
                                                            className="px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded-md border border-blue-100"
                                                        >
                                                            {role.name}
                                                        </span>
                                                    ))
                                                ) : (
                                                    <span className="text-gray-400 text-sm italic">No roles assigned</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-gray-600">
                                            {user.roles ? user.roles.length : 0}
                                        </td>
                                    </tr>
                                ))}
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan="3" className="px-6 py-8 text-center text-gray-500">
                                            No users found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination - Simplified */}
                {users.links && users.links.length > 3 && (
                    <div className="flex justify-center mt-4">
                        <div className="flex gap-1">
                            {users.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveState
                                    preserveScroll
                                    className={`px-3 py-1 rounded border ${link.active
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'} 
                                        ${!link.url && 'opacity-50 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
