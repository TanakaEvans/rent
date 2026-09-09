import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function CompanyIndex({ auth, company, employees = [] }) {
    const [isEditing, setIsEditing] = useState(!company);

    const { data, setData, post, processing, errors } = useForm({
        name: company?.name || '',
        trading_name: company?.trading_name || '',
        registration_number: company?.registration_number || '',
        tax_number: company?.tax_number || '',
        email: company?.email || '',
        phone: company?.phone || '',
        website: company?.website || '',
        address: company?.address || '',
        city: company?.city || '',
        state: company?.state || '',
        country: company?.country || 'Zimbabwe',
        postal_code: company?.postal_code || '',
        currency: company?.currency || 'USD',
        head_id: company?.head_id || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.company.store'), {
            onSuccess: () => setIsEditing(false),
        });
    };

    const handleLogoUpload = (e) => {
        const file = e.target.files[0];
        if (file) {
            router.post(route('admin.company.logo'), {
                logo: file,
            }, {
                forceFormData: true,
                preserveScroll: true,
            });
        }
    };

    return (
        <AdminLayout title="Company Details" auth={auth}>
            <Head title="Company Details" />

            <div className="max-w-4xl mx-auto">
                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    {/* Header */}
                    <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900">Company Information</h2>
                            <p className="text-sm text-gray-500 mt-1">Manage your company details and branding</p>
                        </div>
                        {company && !isEditing && (
                            <button
                                onClick={() => setIsEditing(true)}
                                className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors"
                            >
                                Edit Details
                            </button>
                        )}
                    </div>

                    {/* Company Logo Section */}
                    {company && (
                        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div className="flex items-center gap-6">
                                <div className="w-24 h-24 bg-gray-200 rounded-lg flex items-center justify-center overflow-hidden">
                                    {company.logo ? (
                                        <img src={`/${company.logo}`} alt="Company Logo" className="w-full h-full object-cover" />
                                    ) : (
                                        <span className="text-4xl">🏢</span>
                                    )}
                                </div>
                                <div>
                                    <h3 className="font-semibold text-gray-900">{company.name}</h3>
                                    {company.trading_name && (
                                        <p className="text-sm text-gray-500">Trading as: {company.trading_name}</p>
                                    )}
                                    <div className="mt-2">
                                        <label className="cursor-pointer text-sm text-orange-600 hover:text-orange-700 font-medium">
                                            <input
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={handleLogoUpload}
                                            />
                                            Change Logo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="p-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Company Name */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Company Name *
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                    required
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                            </div>

                            {/* Trading Name */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Trading Name
                                </label>
                                <input
                                    type="text"
                                    value={data.trading_name}
                                    onChange={(e) => setData('trading_name', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
                            </div>

                            {/* Registration Number */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Registration Number
                                </label>
                                <input
                                    type="text"
                                    value={data.registration_number}
                                    onChange={(e) => setData('registration_number', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
                            </div>

                            {/* Tax Number */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Tax Number (VAT/TIN)
                                </label>
                                <input
                                    type="text"
                                    value={data.tax_number}
                                    onChange={(e) => setData('tax_number', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
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
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
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
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
                            </div>

                            {/* Website */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Website
                                </label>
                                <input
                                    type="url"
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
                            </div>

                            {/* Currency */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Currency
                                </label>
                                <select
                                    value={data.currency}
                                    onChange={(e) => setData('currency', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                >
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="ZWL">ZWL - Zimbabwe Dollar</option>
                                    <option value="ZAR">ZAR - South African Rand</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="GBP">GBP - British Pound</option>
                                </select>
                            </div>

                            {/* Company Head */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Company Head / CEO
                                </label>
                                <select
                                    value={data.head_id}
                                    onChange={(e) => setData('head_id', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                >
                                    <option value="">-- Select Head (Optional) --</option>
                                    {employees.map(emp => (
                                        <option key={emp.id} value={emp.id}>
                                            {emp.first_name} {emp.last_name} - {emp.job_title || 'No Title'}
                                        </option>
                                    ))}
                                </select>
                                {employees.length === 0 && (
                                    <p className="mt-1 text-sm text-gray-500">Create employees first to assign a company head.</p>
                                )}
                                {errors.head_id && <p className="mt-1 text-sm text-red-600">{errors.head_id}</p>}
                            </div>

                            {/* Address - Full Width */}
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Address
                                </label>
                                <textarea
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    disabled={!isEditing}
                                    rows={3}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
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
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
                            </div>

                            {/* State/Province */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    State/Province
                                </label>
                                <input
                                    type="text"
                                    value={data.state}
                                    onChange={(e) => setData('state', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
                            </div>

                            {/* Country */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Country
                                </label>
                                <input
                                    type="text"
                                    value={data.country}
                                    onChange={(e) => setData('country', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
                            </div>

                            {/* Postal Code */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Postal Code
                                </label>
                                <input
                                    type="text"
                                    value={data.postal_code}
                                    onChange={(e) => setData('postal_code', e.target.value)}
                                    disabled={!isEditing}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100"
                                />
                            </div>
                        </div>

                        {/* Form Actions */}
                        {isEditing && (
                            <div className="mt-6 flex justify-end gap-3">
                                {company && (
                                    <button
                                        type="button"
                                        onClick={() => setIsEditing(false)}
                                        className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                )}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : 'Save Company Details'}
                                </button>
                            </div>
                        )}
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
