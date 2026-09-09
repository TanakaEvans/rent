import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RolesShow({ auth, role }) {
    return (
        <AdminLayout title={`Role: ${role.name}`} auth={auth}>
            <Head title={`Role: ${role.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('auth.roles.index')}
                            className="text-gray-600 hover:text-gray-800 flex items-center gap-2"
                        >
                            ← Back to Roles
                        </Link>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href={route('auth.roles.edit', role.id)}
                            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
                        >
                            ✏️ Edit Role
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Role Details */}
                    <div className="lg:col-span-1">
                        <div className="bg-white rounded-xl shadow-sm p-6">
                            <h2 className="text-xl font-bold text-gray-900 mb-4">Role Details</h2>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Role Name
                                    </label>
                                    <div className="text-lg font-semibold text-gray-900">{role.name}</div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Description
                                    </label>
                                    <div className="text-gray-600">
                                        {role.description || 'No description provided'}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Created
                                    </label>
                                    <div className="text-gray-600">
                                        {new Date(role.created_at).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Last Updated
                                    </label>
                                    <div className="text-gray-600">
                                        {new Date(role.updated_at).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Assigned Users */}
                    <div className="lg:col-span-2">
                        <div className="bg-white rounded-xl shadow-sm p-6">
                            <h2 className="text-xl font-bold text-gray-900 mb-4">
                                Assigned Users ({role.users?.length || 0})
                            </h2>

                            {role.users && role.users.length > 0 ? (
                                <div className="space-y-3">
                                    {role.users.map((user) => (
                                        <div key={user.id} className="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                            <div className="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                                {user.name.charAt(0)}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="font-medium text-gray-900 truncate">{user.name}</div>
                                                <div className="text-sm text-gray-500 truncate">{user.email}</div>
                                                <div className="text-sm text-gray-500">@{user.username}</div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className={`px-2 py-1 text-xs rounded-full ${
                                                    user.status === 'active'
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-gray-100 text-gray-600'
                                                }`}>
                                                    {user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                                                </span>
                                                <Link
                                                    href={route('auth.users.show', user.id)}
                                                    className="text-blue-600 hover:text-blue-800 px-2 py-1 rounded transition-colors"
                                                    title="View User"
                                                >
                                                    👁️
                                                </Link>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-gray-500 text-lg mb-2">No users assigned</div>
                                    <div className="text-gray-400">This role has no users assigned to it yet.</div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
