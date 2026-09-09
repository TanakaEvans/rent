import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function DepartmentCreate({ auth, branches, employees = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        branch_id: branches[0]?.id || '',
        name: '',
        code: '',
        description: '',
        head_id: '',
        status: 'active',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.departments.store'));
    };

    return (
        <AdminLayout title="Create Department" auth={auth}>
            <Head title="Create Department" />

            <div className="max-w-3xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <Link
                        href={route('admin.departments.index')}
                        className="text-orange-600 hover:text-orange-700 flex items-center gap-1 mb-2"
                    >
                        ← Back to Departments
                    </Link>
                    <h2 className="text-2xl font-bold text-gray-900">Create New Department</h2>
                </div>

                {/* Form */}
                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <form onSubmit={handleSubmit} className="p-6 space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Branch Selection */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Branch *
                                </label>
                                <select
                                    value={data.branch_id}
                                    onChange={(e) => setData('branch_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required
                                >
                                    <option value="">Select Branch</option>
                                    {branches.map((branch) => (
                                        <option key={branch.id} value={branch.id}>
                                            {branch.name} {branch.company && `(${branch.company.name})`}
                                        </option>
                                    ))}
                                </select>
                                {errors.branch_id && <p className="mt-1 text-sm text-red-600">{errors.branch_id}</p>}
                            </div>

                            {/* Status */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Status *
                                </label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            {/* Department Name */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Department Name *
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                            </div>

                            {/* Department Code */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Department Code *
                                </label>
                                <input
                                    type="text"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono"
                                    placeholder="e.g., KITCHEN"
                                    required
                                />
                                {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                            </div>

                            {/* Department Head */}
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Department Head
                                </label>
                                <select
                                    value={data.head_id}
                                    onChange={(e) => setData('head_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                >
                                    <option value="">-- Select Head (Optional) --</option>
                                    {employees.map(emp => (
                                        <option key={emp.id} value={emp.id}>
                                            {emp.first_name} {emp.last_name} - {emp.job_title || 'No Title'}
                                        </option>
                                    ))}
                                </select>
                                {employees.length === 0 && (
                                    <p className="mt-1 text-sm text-gray-500">Create employees first to assign a department head.</p>
                                )}
                                {errors.head_id && <p className="mt-1 text-sm text-red-600">{errors.head_id}</p>}
                            </div>

                            {/* Description */}
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Description
                                </label>
                                <textarea
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    placeholder="Brief description of the department..."
                                />
                            </div>
                        </div>

                        {/* Form Actions */}
                        <div className="flex justify-end gap-3 pt-6 border-t border-gray-200">
                            <Link
                                href={route('admin.departments.index')}
                                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Creating...' : 'Create Department'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
