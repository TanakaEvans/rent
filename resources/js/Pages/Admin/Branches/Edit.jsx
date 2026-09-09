import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function BranchEdit({ auth, branch, companies, employees = [] }) {
    const { data, setData, patch, processing, errors } = useForm({
        company_id: branch.company_id || '',
        name: branch.name || '',
        code: branch.code || '',
        email: branch.email || '',
        phone: branch.phone || '',
        address: branch.address || '',
        city: branch.city || '',
        head_id: branch.head_id || '',
        is_main_branch: branch.is_main_branch || false,
        status: branch.status || 'active',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(route('admin.branches.update', branch.id));
    };

    return (
        <AdminLayout title="Edit Branch" auth={auth}>
            <Head title="Edit Branch" />

            <div className="max-w-3xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <Link
                        href={route('admin.branches.index')}
                        className="text-orange-600 hover:text-orange-700 flex items-center gap-1 mb-2"
                    >
                        ← Back to Branches
                    </Link>
                    <h2 className="text-2xl font-bold text-gray-900">Edit Branch: {branch.name}</h2>
                </div>

                {/* Error Summary */}
                {Object.keys(errors).length > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl">❌</span>
                            <div>
                                <h3 className="font-semibold text-red-900">Please fix the following errors:</h3>
                                <ul className="mt-2 list-disc list-inside text-sm text-red-700 space-y-1">
                                    {Object.entries(errors).map(([field, message]) => (
                                        <li key={field}><strong>{field}:</strong> {message}</li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                )}

                {/* Form */}
                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <form onSubmit={handleSubmit} className="p-6 space-y-6">
                        {/* Company Selection */}
                        {companies.length > 1 && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Company *
                                </label>
                                <select
                                    value={data.company_id}
                                    onChange={(e) => setData('company_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required
                                >
                                    {companies.map((company) => (
                                        <option key={company.id} value={company.id}>
                                            {company.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.company_id && <p className="mt-1 text-sm text-red-600">{errors.company_id}</p>}
                            </div>
                        )}

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Branch Name */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Branch Name *
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

                            {/* Branch Code */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Branch Code *
                                </label>
                                <input
                                    type="text"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono"
                                    required
                                />
                                {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                            </div>

                            {/* Email */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                            </div>

                            {/* Phone */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                                {errors.phone && <p className="mt-1 text-sm text-red-600">{errors.phone}</p>}
                            </div>

                            {/* City */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    City
                                </label>
                                <input
                                    type="text"
                                    value={data.city}
                                    onChange={(e) => setData('city', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                                {errors.city && <p className="mt-1 text-sm text-red-600">{errors.city}</p>}
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

                            {/* Address - Full Width */}
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Address
                                </label>
                                <textarea
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    rows={3}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                            </div>

                            {/* Branch Head/Manager */}
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Branch Manager
                                </label>
                                <select
                                    value={data.head_id}
                                    onChange={(e) => setData('head_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                >
                                    <option value="">-- Select Manager (Optional) --</option>
                                    {employees.map(emp => (
                                        <option key={emp.id} value={emp.id}>
                                            {emp.first_name} {emp.last_name} - {emp.job_title || 'No Title'}
                                        </option>
                                    ))}
                                </select>
                                {employees.length === 0 && (
                                    <p className="mt-1 text-sm text-gray-500">Create employees first to assign a branch manager.</p>
                                )}
                                {errors.head_id && <p className="mt-1 text-sm text-red-600">{errors.head_id}</p>}
                            </div>

                            {/* Main Branch Checkbox */}
                            <div className="md:col-span-2">
                                <label className="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        checked={data.is_main_branch}
                                        onChange={(e) => setData('is_main_branch', e.target.checked)}
                                        className="w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500"
                                    />
                                    <span className="text-sm text-gray-700">
                                        Set as Main Branch (Headquarters)
                                    </span>
                                </label>
                            </div>
                        </div>

                        {/* Form Actions */}
                        <div className="flex justify-end gap-3 pt-6 border-t border-gray-200">
                            <Link
                                href={route('admin.branches.index')}
                                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
