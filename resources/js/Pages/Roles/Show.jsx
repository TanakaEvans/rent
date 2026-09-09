import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RolesShow({ auth, role, routesByModule = {} }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedModule, setSelectedModule] = useState('all');
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

    const getMethodColor = (method) => {
        const colors = {
            'GET': 'bg-green-100 text-green-800',
            'POST': 'bg-blue-100 text-blue-800',
            'PUT': 'bg-yellow-100 text-yellow-800',
            'PATCH': 'bg-orange-100 text-orange-800',
            'DELETE': 'bg-red-100 text-red-800'
        };
        return colors[method] || 'bg-gray-100 text-gray-800';
    };

    // Filter routes based on search term
    const filteredRoutesByModule = Object.fromEntries(
        Object.entries(routesByModule).map(([module, routes]) => [
            module,
            routes.filter(route =>
                route.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                route.description.toLowerCase().includes(searchTerm.toLowerCase())
            )
        ]).filter(([module, routes]) =>
            routes.length > 0 && (selectedModule === 'all' || selectedModule === module)
        )
    );

    const totalRoutes = Object.values(routesByModule).flat().length;
    const totalModules = Object.keys(routesByModule).length;

    return (
        <AdminLayout title={`Role: ${role.name}`} auth={auth}>
            <Head title={`Role: ${role.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="bg-white rounded-xl shadow-sm p-6">
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div className="flex items-start gap-4">
                            <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center text-white text-2xl font-bold">
                                {role.name.charAt(0)}
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">{role.name}</h1>
                                <p className="text-gray-600 mt-1">
                                    {role.description || 'No description provided'}
                                </p>
                                <div className="flex items-center gap-4 mt-2 text-sm text-gray-500">
                                    <span>🔑 {totalRoutes} permissions</span>
                                    <span>📁 {totalModules} modules</span>
                                    <span>👥 {role.users?.length || 0} users</span>
                                    <span>📅 Created {new Date(role.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Link
                                href={route('auth.roles.edit', role.id)}
                                className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
                            >
                                ✏️ Edit Role
                            </Link>
                            <Link
                                href={route('auth.roles.index')}
                                className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
                            >
                                ← Back to Roles
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    {/* Sidebar - Role Info & Users */}
                    <div className="lg:col-span-1 space-y-6">
                        {/* Quick Stats */}
                        <div className="bg-white rounded-xl shadow-sm p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">📊 Quick Stats</h3>
                            <div className="space-y-3">
                                <div className="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                    <span className="text-blue-700 font-medium">Total Permissions</span>
                                    <span className="text-blue-900 font-bold">{totalRoutes}</span>
                                </div>
                                <div className="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                                    <span className="text-green-700 font-medium">Active Modules</span>
                                    <span className="text-green-900 font-bold">{totalModules}</span>
                                </div>
                                <div className="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                                    <span className="text-purple-700 font-medium">Assigned Users</span>
                                    <span className="text-purple-900 font-bold">{role.users?.length || 0}</span>
                                </div>
                            </div>
                        </div>

                        {/* Module Filter */}
                        <div className="bg-white rounded-xl shadow-sm p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">🗂️ Filter by Module</h3>
                            <select
                                value={selectedModule}
                                onChange={(e) => setSelectedModule(e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="all">All Modules ({totalModules})</option>
                                {Object.entries(routesByModule).map(([module, routes]) => (
                                    <option key={module} value={module}>
                                        {getModuleIcon(module)} {module} ({routes.length})
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Assigned Users */}
                        <div className="bg-white rounded-xl shadow-sm p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                👥 Assigned Users ({role.users?.length || 0})
                            </h3>
                            {role.users && role.users.length > 0 ? (
                                <div className="space-y-3 max-h-64 overflow-y-auto">
                                    {role.users.map((user) => (
                                        <Link
                                            key={user.id}
                                            href={route('auth.users.show', user.id)}
                                            className="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg transition-colors group"
                                        >
                                            <div className="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                                {user.name.charAt(0)}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="font-medium text-gray-900 text-sm truncate group-hover:text-blue-600">{user.name}</div>
                                                <div className="text-xs text-gray-500 truncate">@{user.username}</div>
                                            </div>
                                            <span className={`px-2 py-0.5 text-xs rounded-full ${user.status === 'active'
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-600'
                                                }`}>
                                                {user.status}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-6">
                                    <div className="text-gray-400 text-lg mb-2">👤</div>
                                    <div className="text-gray-500 text-sm">No users assigned</div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Main Content - Route Permissions */}
                    <div className="lg:col-span-3">
                        <div className="bg-white rounded-xl shadow-sm p-6">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                                <div>
                                    <h2 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                                        🔑 Accessible Routes & Permissions
                                    </h2>
                                    <p className="text-gray-600 mt-1">
                                        This role has access to {totalRoutes} routes across {totalModules} modules
                                    </p>
                                </div>
                                <div className="flex-shrink-0">
                                    <input
                                        type="text"
                                        placeholder="Search permissions..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="w-64 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            </div>

                            {totalRoutes === 0 ? (
                                <div className="text-center py-12">
                                    <div className="text-gray-400 text-6xl mb-4">🔒</div>
                                    <div className="text-xl font-semibold text-gray-700 mb-2">No Permissions Assigned</div>
                                    <div className="text-gray-500 mb-4">This role has no route permissions assigned.</div>
                                    <Link
                                        href={route('auth.roles.edit', role.id)}
                                        className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors"
                                    >
                                        Assign Permissions
                                    </Link>
                                </div>
                            ) : Object.keys(filteredRoutesByModule).length === 0 ? (
                                <div className="text-center py-12">
                                    <div className="text-gray-400 text-lg mb-2">🔍</div>
                                    <div className="text-gray-600">No routes found matching your search criteria.</div>
                                </div>
                            ) : (
                                <div className="space-y-6">
                                    {Object.entries(filteredRoutesByModule).map(([module, routes]) => (
                                        <div key={module} className={`border-2 rounded-xl p-6 transition-all ${getModuleColor(module)}`}>
                                            <div className="flex items-center justify-between mb-4">
                                                <div className="flex items-center gap-3">
                                                    <span className="text-2xl">{getModuleIcon(module)}</span>
                                                    <div>
                                                        <h3 className="text-xl font-bold text-gray-900">{module}</h3>
                                                        <p className="text-sm text-gray-600">{routes.length} accessible routes</p>
                                                    </div>
                                                </div>
                                                <span className="bg-white px-3 py-1 rounded-full text-sm font-semibold text-gray-700">
                                                    {routes.length} routes
                                                </span>
                                            </div>

                                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                                {routes.map((route) => (
                                                    <div key={route.id} className="bg-white/70 backdrop-blur-sm rounded-lg p-4 hover:bg-white/90 transition-all hover:shadow-sm">
                                                        <div className="flex items-start justify-between mb-2">
                                                            <div className="flex-1">
                                                                <div className="text-sm font-semibold text-gray-900 mb-1">
                                                                    {route.name}
                                                                </div>
                                                                <div className="text-xs text-gray-600 mb-2 line-clamp-2">
                                                                    {route.description}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <div className="flex items-center gap-2">
                                                                {typeof route.methods === 'string' && route.methods.split('|').map((method) => (
                                                                    <span key={method} className={`px-2 py-0.5 text-xs rounded font-medium ${getMethodColor(method)}`}>
                                                                        {method}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                            <div className="text-xs text-gray-500 font-mono">
                                                                /{route.uri}
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
