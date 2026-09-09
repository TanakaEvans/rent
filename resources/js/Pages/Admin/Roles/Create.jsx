import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Button from '@/Components/Button';

export default function RolesCreate({ auth, routesByModule = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        permissions: []
    });

    const [selectedRoutes, setSelectedRoutes] = useState([]);

    const handleRouteToggle = (routeName) => {
        setSelectedRoutes(prev => {
            if (prev.includes(routeName)) {
                return prev.filter(r => r !== routeName);
            } else {
                return [...prev, routeName];
            }
        });
    };

    const submit = (e) => {
        e.preventDefault();

        const formData = {
            ...data,
            permissions: selectedRoutes
        };

        console.log('Submitting role data:', formData);
        console.log('Selected routes:', selectedRoutes);
        console.log('Form data:', data);

        post(route('auth.roles.store'), formData, {
            onStart: () => console.log('Form submission started'),
            onSuccess: (response) => console.log('Form submission successful:', response),
            onError: (errors) => console.log('Form submission errors:', errors),
            onFinish: () => console.log('Form submission finished')
        });
    };

    return (
        <AdminLayout title="Create Role" auth={auth}>
            <Head title="Create Role" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link
                        href={route('auth.roles.index')}
                        className="text-gray-600 hover:text-gray-800 flex items-center gap-2"
                    >
                        ← Back to Roles
                    </Link>
                </div>

                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Create New Role</h1>
                    <p className="text-gray-600">Add a new role to the system and assign permissions</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Role Details */}
                    <div className="bg-white rounded-xl shadow-sm p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Role Information</h2>
                        <form onSubmit={submit} className="space-y-6">
                            <div>
                                <InputLabel htmlFor="name" value="Role Name" />
                                <TextInput
                                    id="name"
                                    name="name"
                                    value={data.name}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Enter role name"
                                    required
                                />
                                <InputError message={errors.name} className="mt-2" />
                            </div>

                            <div>
                                <InputLabel htmlFor="description" value="Description (Optional)" />
                                <textarea
                                    id="description"
                                    name="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Enter role description"
                                    rows={4}
                                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <InputError message={errors.description} className="mt-2" />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Role'}
                                </Button>
                                <Link
                                    href={route('auth.roles.index')}
                                    className="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </form>
                    </div>

                    {/* Permissions */}
                    <div className="bg-white rounded-xl shadow-sm p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">
                            Route Permissions ({selectedRoutes.length} selected)
                        </h2>

                        <div className="space-y-4 max-h-96 overflow-y-auto">
                            {Object.entries(routesByModule).map(([module, routes]) => (
                                <div key={module} className="border rounded-lg p-4">
                                    <h3 className="font-medium text-gray-900 mb-3 flex items-center gap-2">
                                        <span className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                                            {module}
                                        </span>
                                        Module
                                    </h3>

                                    <div className="space-y-2">
                                        {routes.map((route) => (
                                            <div key={route.name} className="flex items-start gap-3 p-2 hover:bg-gray-50 rounded">
                                                <input
                                                    type="checkbox"
                                                    id={route.name}
                                                    checked={selectedRoutes.includes(route.name)}
                                                    onChange={() => handleRouteToggle(route.name)}
                                                    className="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                />
                                                <div className="flex-1 min-w-0">
                                                    <label
                                                        htmlFor={route.name}
                                                        className="text-sm font-medium text-gray-900 cursor-pointer"
                                                    >
                                                        <Link
                                                            href={`/${route.uri}`}
                                                            className="text-blue-600 hover:text-blue-800 hover:underline"
                                                            target="_blank"
                                                        >
                                                            {route.name}
                                                        </Link>
                                                    </label>
                                                    <div className="text-xs text-gray-500 mt-1">
                                                        {route.description || 'No description available'}
                                                    </div>
                                                    <div className="text-xs text-gray-400 mt-1">
                                                        <span className="px-1 py-0.5 bg-gray-100 rounded">
                                                            {route.methods}
                                                        </span>
                                                        <span className="ml-2">/{route.uri}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}

                            {Object.keys(routesByModule).length === 0 && (
                                <div className="text-center py-8 text-gray-500">
                                    <p>No routes available.</p>
                                    <p className="text-sm">Run <code>php artisan routes:extract</code> to populate routes.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
