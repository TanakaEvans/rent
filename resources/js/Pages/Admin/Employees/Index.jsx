import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function EmployeesIndex({ auth, employees, branches, departments, filters }) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.employees.index'), { search }, { preserveState: true });
    };

    const handleFilter = (key, value) => {
        router.get(route('admin.employees.index'), { ...filters, [key]: value }, { preserveState: true });
    };

    const handleDelete = (employee) => {
        if (confirm(`Are you sure you want to delete "${employee.first_name} ${employee.last_name}"?`)) {
            router.delete(route('admin.employees.destroy', employee.id));
        }
    };

    const handleToggleStatus = (employee) => {
        router.patch(route('admin.employees.toggle-status', employee.id));
    };

    const getStatusColor = (status) => {
        const colors = {
            active: 'bg-green-100 text-green-800',
            inactive: 'bg-gray-100 text-gray-800',
            terminated: 'bg-red-100 text-red-800',
            suspended: 'bg-yellow-100 text-yellow-800',
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    return (
        <AdminLayout title="Employees" auth={auth}>
            <Head title="Employees" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">Employees</h2>
                        <p className="text-gray-600">Manage employee records and user accounts</p>
                    </div>
                    <Link
                        href={route('admin.employees.create')}
                        className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors flex items-center gap-2"
                    >
                        <span>+</span>
                        Add Employee
                    </Link>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl shadow-sm p-4">
                    <div className="flex flex-wrap gap-4">
                        {/* Search */}
                        <form onSubmit={handleSearch} className="flex-1 min-w-[200px]">
                            <div className="relative">
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search employees..."
                                    className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                                <span className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                    🔍
                                </span>
                            </div>
                        </form>

                        {/* Branch Filter */}
                        <select
                            value={filters.branch_id || ''}
                            onChange={(e) => handleFilter('branch_id', e.target.value)}
                            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        >
                            <option value="">All Branches</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name}
                                </option>
                            ))}
                        </select>

                        {/* Department Filter */}
                        <select
                            value={filters.department_id || ''}
                            onChange={(e) => handleFilter('department_id', e.target.value)}
                            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        >
                            <option value="">All Departments</option>
                            {departments.map((department) => (
                                <option key={department.id} value={department.id}>
                                    {department.name}
                                </option>
                            ))}
                        </select>

                        {/* Status Filter */}
                        <select
                            value={filters.status || ''}
                            onChange={(e) => handleFilter('status', e.target.value)}
                            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="terminated">Terminated</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                {/* Employees Table */}
                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Employee
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID / Job Title
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Department
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Contact
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User Account
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
                            {employees.data.length > 0 ? (
                                employees.data.map((employee) => (
                                    <tr key={employee.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <div className="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center overflow-hidden">
                                                    {employee.photo ? (
                                                        <img src={`/${employee.photo}`} alt="" className="w-full h-full object-cover" />
                                                    ) : (
                                                        <span className="text-gray-500 font-semibold">
                                                            {employee.first_name.charAt(0)}{employee.last_name.charAt(0)}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="ml-4">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {employee.first_name} {employee.last_name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">{employee.email || '-'}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-mono text-gray-900">{employee.employee_number}</div>
                                            <div className="text-sm text-gray-500">{employee.job_title || '-'}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900">{employee.department?.name || '-'}</div>
                                            <div className="text-sm text-gray-500">{employee.branch?.name || '-'}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900">{employee.phone || '-'}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {employee.user ? (
                                                <div>
                                                    <span className="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
                                                        Has Account
                                                    </span>
                                                    <div className="text-xs text-gray-500 mt-1">
                                                        {employee.user.roles?.map(r => r.name).join(', ') || 'No roles'}
                                                    </div>
                                                </div>
                                            ) : (
                                                <Link
                                                    href={route('admin.employees.create-user', employee.id)}
                                                    className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full hover:bg-blue-200"
                                                >
                                                    Create Account
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <button
                                                onClick={() => handleToggleStatus(employee)}
                                                className={`px-3 py-1 text-xs font-semibold rounded-full ${getStatusColor(employee.status)}`}
                                            >
                                                {employee.status}
                                            </button>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end gap-2">
                                                <Link
                                                    href={route('admin.employees.show', employee.id)}
                                                    className="text-gray-600 hover:text-gray-900"
                                                >
                                                    View
                                                </Link>
                                                <Link
                                                    href={route('admin.employees.edit', employee.id)}
                                                    className="text-orange-600 hover:text-orange-900"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    onClick={() => handleDelete(employee)}
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
                                    <td colSpan="7" className="px-6 py-12 text-center text-gray-500">
                                        No employees found. Add your first employee to get started.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {employees.links && employees.links.length > 3 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                            <div className="text-sm text-gray-500">
                                Showing {employees.from} to {employees.to} of {employees.total} results
                            </div>
                            <div className="flex gap-2">
                                {employees.links.map((link, index) => (
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
