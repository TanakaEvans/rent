import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthManagement({ auth, users }) {
    const [searchTerm, setSearchTerm] = useState('');

    const handleReset = (user) => {
        if (confirm(`Are you sure you want to reset the password for ${user.name}? This will set it to their EmployeeID + Surname.`)) {
            router.post(route('auth.management.reset', user.id));
        }
    };

    const handleToggleStatus = (user) => {
        const action = user.status === 'active' ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} ${user.name}?`)) {
            router.patch(route('auth.management.toggle-status', user.id));
        }
    };

    const filteredUsers = users.data.filter(user =>
        user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
        user.username.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <AdminLayout title="Auth Management" auth={auth}>
            <Head title="Auth Management" />

            <div className="space-y-6">
                <div className="bg-white rounded-xl shadow-sm p-6">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">User Authentication Management</h1>
                            <p className="text-gray-600">Manage user access, reset passwords, and lock accounts.</p>
                        </div>
                        <div className="w-full md:w-64">
                            <input
                                type="text"
                                placeholder="Search users..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
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
                                {filteredUsers.map((user) => (
                                    <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3">
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
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles && user.roles.map(role => (
                                                    <span key={role.id} className="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-600">
                                                        {role.name}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${user.status === 'active'
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-red-100 text-red-700'
                                                }`}>
                                                {user.status === 'active' ? 'Active' : 'Locked'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => handleReset(user)}
                                                    className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                                    title="Reset Password to Default"
                                                >
                                                    🔑 Reset Pass
                                                </button>
                                                <button
                                                    onClick={() => handleToggleStatus(user)}
                                                    className={`px-3 py-1.5 text-xs font-medium text-white rounded-lg transition-colors ${user.status === 'active'
                                                            ? 'bg-orange-500 hover:bg-orange-600'
                                                            : 'bg-green-600 hover:bg-green-700'
                                                        }`}
                                                >
                                                    {user.status === 'active' ? '🔒 Lock' : '🔓 Unlock'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {users.links && (
                        <div className="mt-6">
                            {/* Pagination links rendering if needed custom or use standard Laravel pagination links via a component */}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
