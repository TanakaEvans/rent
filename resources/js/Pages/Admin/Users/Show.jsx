import React from 'react';
import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Show({ user }) {
    return (
        <AdminLayout>
            <Head title={`User: ${user?.name || 'Unknown'}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h1 className="text-2xl font-semibold">User Details</h1>
                                <div className="space-x-2">
                                    <a
                                        href={route('auth.users.edit', user?.id)}
                                        className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                                    >
                                        Edit User
                                    </a>
                                    <a
                                        href={route('auth.users.index')}
                                        className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                    >
                                        Back to Users
                                    </a>
                                </div>
                            </div>

                            {user ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {/* Basic Information */}
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h2 className="text-lg font-semibold mb-4">Basic Information</h2>
                                        <div className="space-y-3">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Name</label>
                                                <p className="text-sm text-gray-900">{user.name || 'N/A'}</p>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Email</label>
                                                <p className="text-sm text-gray-900">{user.email || 'N/A'}</p>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Username</label>
                                                <p className="text-sm text-gray-900">{user.username || 'N/A'}</p>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Status</label>
                                                <span
                                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                        user.status === 'active'
                                                            ? 'bg-green-100 text-green-800'
                                                            : 'bg-red-100 text-red-800'
                                                    }`}
                                                >
                                                    {user.status === 'active' ? 'Active' : 'Inactive'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Role Information */}
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h2 className="text-lg font-semibold mb-4">Assigned Roles</h2>
                                        <div className="space-y-2">
                                            {user.roles && user.roles.length > 0 ? (
                                                user.roles.map((role) => (
                                                    <div key={role.id} className="flex items-center justify-between p-2 bg-white rounded border">
                                                        <div>
                                                            <span className="font-medium text-blue-600">{role.name}</span>
                                                            {role.description && (
                                                                <p className="text-sm text-gray-500">{role.description}</p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))
                                            ) : (
                                                <p className="text-sm text-gray-500">No roles assigned</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Account Information */}
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h2 className="text-lg font-semibold mb-4">Account Information</h2>
                                        <div className="space-y-3">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Email Verified</label>
                                                <p className="text-sm text-gray-900">
                                                    {user.email_verified_at ? 'Yes' : 'No'}
                                                    {user.email_verified_at && (
                                                        <span className="text-gray-500 ml-2">
                                                            ({new Date(user.email_verified_at).toLocaleDateString()})
                                                        </span>
                                                    )}
                                                </p>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Created</label>
                                                <p className="text-sm text-gray-900">
                                                    {user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}
                                                </p>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Last Updated</label>
                                                <p className="text-sm text-gray-900">
                                                    {user.updated_at ? new Date(user.updated_at).toLocaleDateString() : 'N/A'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Additional Information */}
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <h2 className="text-lg font-semibold mb-4">Additional Information</h2>
                                        <div className="space-y-3">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">User ID</label>
                                                <p className="text-sm text-gray-900">{user.id}</p>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Total Roles</label>
                                                <p className="text-sm text-gray-900">{user.roles ? user.roles.length : 0}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <p className="text-gray-500">User not found.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
