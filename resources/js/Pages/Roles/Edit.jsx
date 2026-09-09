import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RolesEdit({ auth, role, routesByModule = {}, assignedRouteIds = [] }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: role.name || '',
        description: role.description || '',
        route_ids: assignedRouteIds
    });

    const [selectedRoutes, setSelectedRoutes] = useState(assignedRouteIds);
    const [searchTerm, setSearchTerm] = useState('');

    const handleRouteToggle = (routeId) => {
        const updatedRoutes = selectedRoutes.includes(routeId)
            ? selectedRoutes.filter(id => id !== routeId)
            : [...selectedRoutes, routeId];

        setSelectedRoutes(updatedRoutes);
        setData('route_ids', updatedRoutes);
    };

    const handleSelectAllModule = (module, routes) => {
        const moduleRouteIds = routes.map(route => route.id);
        const allSelected = moduleRouteIds.every(id => selectedRoutes.includes(id));

        let updatedRoutes;
        if (allSelected) {
            updatedRoutes = selectedRoutes.filter(id => !moduleRouteIds.includes(id));
        } else {
            updatedRoutes = [...new Set([...selectedRoutes, ...moduleRouteIds])];
        }

        setSelectedRoutes(updatedRoutes);
        setData('route_ids', updatedRoutes);
    };

    const filteredRoutesByModule = Object.fromEntries(
        Object.entries(routesByModule).map(([module, routes]) => [
            module,
            routes.filter(route =>
                route.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (route.description && route.description.toLowerCase().includes(searchTerm.toLowerCase()))
            )
        ]).filter(([module, routes]) => routes.length > 0)
    );

    const submit = (e) => {
        e.preventDefault();
        patch(route('auth.roles.update', role.id));
    };

    const getModuleIcon = (module) => {
        const icons = {
            'Authentication & User Management': '🔐',
            'Dashboard & Analytics': '📊',
            'Academic Management': '📚',
            'Student Portal': '🎓',
            'Teacher Portal': '👨‍🏫',
            'Parent Portal': '👨‍👩‍👧‍👦',
            'Staff Management': '👔',
            'Financial Management': '💰',
            'Communication': '💬',
            'System Administration': '⚙️',
            'General': '📁'
        };
        return icons[module] || '📁';
    };

    const getModuleColor = (module) => {
        const colors = {
            'Authentication & User Management': 'border-blue-200 bg-blue-50',
            'Dashboard & Analytics': 'border-green-200 bg-green-50',
            'Academic Management': 'border-purple-200 bg-purple-50',
            'Student Portal': 'border-indigo-200 bg-indigo-50',
            'Teacher Portal': 'border-cyan-200 bg-cyan-50',
            'Parent Portal': 'border-pink-200 bg-pink-50',
            'Staff Management': 'border-yellow-200 bg-yellow-50',
            'Financial Management': 'border-emerald-200 bg-emerald-50',
            'Communication': 'border-orange-200 bg-orange-50',
            'System Administration': 'border-red-200 bg-red-50',
            'General': 'border-gray-200 bg-gray-50'
        };
        return colors[module] || 'border-gray-200 bg-gray-50';
    };

    return (
        <AdminLayout title={`Edit Role: ${role.name}`} auth={auth}>
            <Head title={`Edit Role: ${role.name}`} />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Edit Role: {role.name}</h1>
                        <p className="text-gray-600">Update role information and permissions</p>
                    </div>
                    <Link
                        href={route('auth.roles.index')}
                        className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2"
                    >
                        ← Back to Roles
                    </Link>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Role Information */}
                    <div className="bg-white rounded-xl shadow-sm p-6">
                        <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            🛡️ Role Information
                        </h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                                    Role Name *
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 ${errors.name ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                    placeholder="Enter role name"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-red-500 text-sm mt-1">{errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
                                    Description (Optional)
                                </label>
                                <input
                                    id="description"
                                    type="text"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 ${errors.description ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                    placeholder="Enter role description"
                                />
                                {errors.description && (
                                    <p className="text-red-500 text-sm mt-1">{errors.description}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Route Permissions */}
                    <div className="bg-white rounded-xl shadow-sm p-6">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <div>
                                <h2 className="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                    🔑 Route Permissions
                                </h2>
                                <p className="text-sm text-gray-600 mt-1">
                                    Select which parts of the system this role can access ({selectedRoutes.length} selected)
                                </p>
                            </div>
                            <div className="flex-shrink-0">
                                <input
                                    type="text"
                                    placeholder="Search routes..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-64 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        {Object.keys(filteredRoutesByModule).length === 0 ? (
                            <div className="text-center py-12">
                                <div className="text-gray-400 text-lg mb-2">🔍</div>
                                <div className="text-gray-600">No routes found. Try adjusting your search.</div>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {Object.entries(filteredRoutesByModule).map(([module, routes]) => {
                                    const moduleRouteIds = routes.map(route => route.id);
                                    const selectedCount = moduleRouteIds.filter(id => selectedRoutes.includes(id)).length;
                                    const isAllSelected = selectedCount === moduleRouteIds.length;

                                    return (
                                        <div key={module} className={`border-2 rounded-lg p-4 transition-all ${getModuleColor(module)}`}>
                                            <div className="flex items-center justify-between mb-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xl">{getModuleIcon(module)}</span>
                                                    <h3 className="font-semibold text-gray-900">{module}</h3>
                                                    <span className="text-xs bg-white px-2 py-1 rounded-full text-gray-600">
                                                        {selectedCount}/{routes.length}
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => handleSelectAllModule(module, routes)}
                                                    className={`text-xs px-3 py-1 rounded-full transition-colors ${isAllSelected
                                                        ? 'bg-red-100 text-red-700 hover:bg-red-200'
                                                        : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
                                                        }`}
                                                >
                                                    {isAllSelected ? 'Deselect All' : 'Select All'}
                                                </button>
                                            </div>

                                            <div className="space-y-2 max-h-64 overflow-y-auto">
                                                {routes.map((route) => (
                                                    <div key={route.id} className="flex items-start gap-3 p-2 hover:bg-white/50 rounded-lg transition-colors">
                                                        <input
                                                            type="checkbox"
                                                            id={`route-${route.id}`}
                                                            checked={selectedRoutes.includes(route.id)}
                                                            onChange={() => handleRouteToggle(route.id)}
                                                            className="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                        />
                                                        <label htmlFor={`route-${route.id}`} className="flex-1 cursor-pointer">
                                                            <div className="text-sm font-medium text-gray-900">
                                                                {route.name}
                                                            </div>
                                                            <div className="text-xs text-gray-600 mt-1">
                                                                {route.description}
                                                            </div>
                                                            <div className="flex items-center gap-2 mt-1">
                                                                <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">
                                                                    {route.methods}
                                                                </span>
                                                                <span className="text-xs text-gray-500">/{route.uri}</span>
                                                            </div>
                                                        </label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-4 pt-6 border-t">
                        <Link
                            href={route('auth.roles.index')}
                            className="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            {processing && (
                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            )}
                            {processing ? 'Updating Role...' : 'Update Role'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
