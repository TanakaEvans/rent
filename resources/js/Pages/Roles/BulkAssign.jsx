import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function BulkAssign({ auth, roles, users }) {
    const { data, setData, post, processing, errors } = useForm({
        role_ids: [],
        user_ids: []
    });

    const [roleSearch, setRoleSearch] = useState('');
    const [userSearch, setUserSearch] = useState('');

    const toggleRole = (id) => {
        const current = data.role_ids;
        if (current.includes(id)) {
            setData('role_ids', current.filter(rid => rid !== id));
        } else {
            setData('role_ids', [...current, id]);
        }
    };

    const toggleUser = (id) => {
        const current = data.user_ids;
        if (current.includes(id)) {
            setData('user_ids', current.filter(uid => uid !== id));
        } else {
            setData('user_ids', [...current, id]);
        }
    };

    const selectAllUsers = () => {
        const filteredUserIds = filteredUsers.map(u => u.id);
        const allSelected = filteredUserIds.every(id => data.user_ids.includes(id));

        if (allSelected) {
            setData('user_ids', data.user_ids.filter(id => !filteredUserIds.includes(id)));
        } else {
            setData('user_ids', [...new Set([...data.user_ids, ...filteredUserIds])]);
        }
    };

    const filteredRoles = roles.filter(r => r.name.toLowerCase().includes(roleSearch.toLowerCase()));
    // Assuming users is a collection, not paginated for this bulk view based on controller code
    const filteredUsers = users.filter(u =>
        u.name.toLowerCase().includes(userSearch.toLowerCase()) ||
        (u.email && u.email.toLowerCase().includes(userSearch.toLowerCase()))
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('roles.bulk-assign.store'));
    };

    return (
        <AdminLayout title="Bulk Assign Roles" auth={auth}>
            <Head title="Bulk Assign Roles" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Bulk Assign Roles</h1>
                        <p className="text-gray-600">Assign multiple roles to multiple users at once.</p>
                    </div>
                    <Link
                        href={route('auth.roles.index')}
                        className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors"
                    >
                        ← Back to Roles
                    </Link>
                </div>

                <form onSubmit={submit} className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Roles Selection */}
                    <div className="bg-white rounded-xl shadow-sm p-6 flex flex-col h-[600px]">
                        <div className="mb-4">
                            <h2 className="text-lg font-semibold text-gray-900 mb-2">1. Select Roles</h2>
                            <input
                                type="text"
                                placeholder="Search roles..."
                                value={roleSearch}
                                onChange={(e) => setRoleSearch(e.target.value)}
                                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                            />
                            {errors.role_ids && <p className="text-red-500 text-sm mt-1">{errors.role_ids}</p>}
                        </div>
                        <div className="flex-1 overflow-y-auto space-y-2 border rounded-lg p-2">
                            {filteredRoles.map(role => (
                                <label key={role.id} className="flex items-center gap-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.role_ids.includes(role.id)}
                                        onChange={() => toggleRole(role.id)}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="text-gray-700 font-medium">{role.name}</span>
                                </label>
                            ))}
                            {filteredRoles.length === 0 && <p className="text-gray-500 text-center py-4">No roles found.</p>}
                        </div>
                        <div className="mt-4 text-sm text-gray-500">
                            {data.role_ids.length} roles selected
                        </div>
                    </div>

                    {/* Users Selection */}
                    <div className="bg-white rounded-xl shadow-sm p-6 flex flex-col h-[600px]">
                        <div className="mb-4">
                            <div className="flex justify-between items-center mb-2">
                                <h2 className="text-lg font-semibold text-gray-900">2. Select Users</h2>
                                <button type="button" onClick={selectAllUsers} className="text-sm text-blue-600 hover:text-blue-800">
                                    Select/Deselect All Found
                                </button>
                            </div>
                            <input
                                type="text"
                                placeholder="Search users by name or email..."
                                value={userSearch}
                                onChange={(e) => setUserSearch(e.target.value)}
                                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                            />
                            {errors.user_ids && <p className="text-red-500 text-sm mt-1">{errors.user_ids}</p>}
                        </div>
                        <div className="flex-1 overflow-y-auto space-y-2 border rounded-lg p-2">
                            {filteredUsers.map(user => (
                                <label key={user.id} className="flex items-center gap-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.user_ids.includes(user.id)}
                                        onChange={() => toggleUser(user.id)}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <div>
                                        <div className="text-gray-700 font-medium">{user.name}</div>
                                        <div className="text-xs text-gray-500">{user.email}</div>
                                        <div className="text-xs text-blue-400">
                                            {user.roles && user.roles.map(r => r.name).join(', ')}
                                        </div>
                                    </div>
                                </label>
                            ))}
                            {filteredUsers.length === 0 && <p className="text-gray-500 text-center py-4">No users found.</p>}
                        </div>
                        <div className="mt-4 text-sm text-gray-500">
                            {data.user_ids.length} users selected
                        </div>
                    </div>

                    <div className="lg:col-span-2 flex justify-end">
                        <button
                            type="submit"
                            disabled={processing || data.role_ids.length === 0 || data.user_ids.length === 0}
                            className="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-semibold shadow-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {processing ? 'Assigning...' : 'Assign Selected Roles to Selected Users'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
