import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Edit({ user, roles }) {
    const { data, setData, put, patch, processing, errors } = useForm({
        name: user.name || '',
        email: user.email || '',
        password: '',
        password_confirmation: '',
        role_ids: user.roles ? user.roles.map(role => role.id) : [],
        is_active: user.is_active !== undefined ? user.is_active : true,
    });

    const submit = (e) => {
        e.preventDefault();
        // Use patch since route definition is Route::patch
        // Route::patch('users/{user}', [UserController::class, 'update'])
        patch(route('auth.users.update', user.id));
    };

    const handleRoleChange = (roleId) => {
        const newRoleIds = data.role_ids.includes(roleId)
            ? data.role_ids.filter(id => id !== roleId)
            : [...data.role_ids, roleId];
        setData('role_ids', newRoleIds);
    };

    return (
        <AdminLayout>
            <Head title={`Edit User - ${user.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h1 className="text-2xl font-semibold">Edit User: {user.name}</h1>
                                <a
                                    href={route('auth.users.index')}
                                    className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                >
                                    Back to Users
                                </a>
                            </div>

                            <form onSubmit={submit} className="space-y-6">
                                {/* Name Field */}
                                <div>
                                    <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                                        Name
                                    </label>
                                    <input
                                        type="text"
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    {errors.name && (
                                        <div className="mt-2 text-sm text-red-600">{errors.name}</div>
                                    )}
                                </div>

                                {/* Email Field */}
                                <div>
                                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        id="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    {errors.email && (
                                        <div className="mt-2 text-sm text-red-600">{errors.email}</div>
                                    )}
                                </div>

                                {/* Password Field */}
                                <div>
                                    <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                                        New Password (leave blank to keep current)
                                    </label>
                                    <input
                                        type="password"
                                        id="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    {errors.password && (
                                        <div className="mt-2 text-sm text-red-600">{errors.password}</div>
                                    )}
                                </div>

                                {/* Confirm Password Field */}
                                <div>
                                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">
                                        Confirm New Password
                                    </label>
                                    <input
                                        type="password"
                                        id="password_confirmation"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    {errors.password_confirmation && (
                                        <div className="mt-2 text-sm text-red-600">{errors.password_confirmation}</div>
                                    )}
                                </div>

                                {/* Roles Selection */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Assign Roles
                                    </label>
                                    <div className="space-y-2">
                                        {roles && roles.map((role) => (
                                            <label key={role.id} className="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    checked={data.role_ids.includes(role.id)}
                                                    onChange={() => handleRoleChange(role.id)}
                                                    className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-offset-0 focus:ring-indigo-200 focus:ring-opacity-50"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">
                                                    {role.name}
                                                    {role.description && (
                                                        <span className="text-gray-500"> - {role.description}</span>
                                                    )}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                    {errors.role_ids && (
                                        <div className="mt-2 text-sm text-red-600">{errors.role_ids}</div>
                                    )}
                                </div>

                                {/* Status Field */}
                                <div>
                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(e) => setData('is_active', e.target.checked)}
                                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-offset-0 focus:ring-indigo-200 focus:ring-opacity-50"
                                        />
                                        <span className="ml-2 text-sm text-gray-700">Active User</span>
                                    </label>
                                    {errors.is_active && (
                                        <div className="mt-2 text-sm text-red-600">{errors.is_active}</div>
                                    )}
                                </div>

                                {/* Submit Button */}
                                <div className="flex items-center justify-end">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline disabled:opacity-50"
                                    >
                                        {processing ? 'Updating...' : 'Update User'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
