import { useState, useEffect } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function EmployeeCreate({ auth, branches, departments, roles, flash }) {
    const [createUserAccount, setCreateUserAccount] = useState(true);
    const [submitError, setSubmitError] = useState(null);
    const { props } = usePage();

    // Log props for debugging
    useEffect(() => {
        console.log('EmployeeCreate props:', { branches, departments, roles, flash });
    }, [branches, departments, roles, flash]);

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        employee_number: '',
        first_name: '',
        last_name: '',
        middle_name: '',
        gender: '',
        date_of_birth: '',
        national_id: '',
        email: '',
        phone: '',
        alt_phone: '',
        address: '',
        city: '',
        branch_id: '',
        department_id: '',
        job_title: '',
        hire_date: '',
        employment_type: 'full_time',
        salary: '',
        bank_name: '',
        bank_account: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        status: 'active',
        create_user_account: true,
        username: '',
        password: '',
        role_ids: [],
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        setSubmitError(null);

        console.log('Submitting employee form with data:', data);

        post(route('admin.employees.store'), {
            onSuccess: () => {
                console.log('Employee created successfully!');
            },
            onError: (errors) => {
                console.error('Form submission errors:', errors);
                setSubmitError('Form submission failed. Please check the errors below.');
            },
            onFinish: () => {
                console.log('Form submission finished. Processing:', processing);
            }
        });
    };

    const handleRoleChange = (roleId) => {
        const newRoles = data.role_ids.includes(roleId)
            ? data.role_ids.filter(id => id !== roleId)
            : [...data.role_ids, roleId];
        setData('role_ids', newRoles);
    };

    return (
        <AdminLayout title="Add Employee" auth={auth}>
            <Head title="Add Employee" />

            <div className="max-w-4xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <Link
                        href={route('admin.employees.index')}
                        className="text-orange-600 hover:text-orange-700 flex items-center gap-1 mb-2"
                    >
                        ← Back to Employees
                    </Link>
                    <h2 className="text-2xl font-bold text-gray-900">Add New Employee</h2>
                </div>

                {/* Flash Messages */}
                {props.flash?.error && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl">❌</span>
                            <div>
                                <h3 className="font-semibold text-red-900">Error</h3>
                                <p className="text-sm text-red-700">{props.flash.error}</p>
                            </div>
                        </div>
                    </div>
                )}

                {props.flash?.success && (
                    <div className="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl">✅</span>
                            <div>
                                <h3 className="font-semibold text-green-900">Success</h3>
                                <p className="text-sm text-green-700">{props.flash.success}</p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Submit Error */}
                {submitError && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl">⚠️</span>
                            <p className="text-red-700">{submitError}</p>
                        </div>
                    </div>
                )}

                {/* Error Summary */}
                {Object.keys(errors).length > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4">
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

                {/* Info about required fields */}
                {(!branches || branches.length === 0) && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl">⚠️</span>
                            <div>
                                <h3 className="font-semibold text-amber-900">No branches available</h3>
                                <p className="text-amber-800 text-sm mt-1">
                                    You need to <Link href={route('admin.branches.create')} className="underline font-medium">create a branch</Link> first
                                    before adding employees.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Personal Information */}
                    <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 className="text-lg font-semibold text-gray-900">Personal Information</h3>
                        </div>
                        <div className="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Employee Number *
                                </label>
                                <input
                                    type="text"
                                    value={data.employee_number}
                                    onChange={(e) => setData('employee_number', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono"
                                    placeholder="e.g., EMP001"
                                    required
                                />
                                {errors.employee_number && <p className="mt-1 text-sm text-red-600">{errors.employee_number}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    First Name *
                                </label>
                                <input
                                    type="text"
                                    value={data.first_name}
                                    onChange={(e) => setData('first_name', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required
                                />
                                {errors.first_name && <p className="mt-1 text-sm text-red-600">{errors.first_name}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Last Name *
                                </label>
                                <input
                                    type="text"
                                    value={data.last_name}
                                    onChange={(e) => setData('last_name', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required
                                />
                                {errors.last_name && <p className="mt-1 text-sm text-red-600">{errors.last_name}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Middle Name
                                </label>
                                <input
                                    type="text"
                                    value={data.middle_name}
                                    onChange={(e) => setData('middle_name', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Gender
                                </label>
                                <select
                                    value={data.gender}
                                    onChange={(e) => setData('gender', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                >
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Date of Birth
                                </label>
                                <input
                                    type="date"
                                    value={data.date_of_birth}
                                    onChange={(e) => setData('date_of_birth', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    National ID
                                </label>
                                <input
                                    type="text"
                                    value={data.national_id}
                                    onChange={(e) => setData('national_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                            </div>

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
                            </div>

                            <div className="md:col-span-3">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Address
                                </label>
                                <textarea
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    rows={2}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Employment Information */}
                    <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 className="text-lg font-semibold text-gray-900">Employment Information</h3>
                        </div>
                        <div className="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Branch
                                </label>
                                <select
                                    value={data.branch_id}
                                    onChange={(e) => setData('branch_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                >
                                    <option value="">Select Branch</option>
                                    {branches.map((branch) => (
                                        <option key={branch.id} value={branch.id}>
                                            {branch.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Department
                                </label>
                                <select
                                    value={data.department_id}
                                    onChange={(e) => setData('department_id', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                >
                                    <option value="">Select Department</option>
                                    {departments.map((dept) => (
                                        <option key={dept.id} value={dept.id}>
                                            {dept.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Job Title
                                </label>
                                <input
                                    type="text"
                                    value={data.job_title}
                                    onChange={(e) => setData('job_title', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    placeholder="e.g., Kitchen Staff"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Employment Type *
                                </label>
                                <select
                                    value={data.employment_type}
                                    onChange={(e) => setData('employment_type', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required
                                >
                                    <option value="full_time">Full Time</option>
                                    <option value="part_time">Part Time</option>
                                    <option value="contract">Contract</option>
                                    <option value="intern">Intern</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Hire Date
                                </label>
                                <input
                                    type="date"
                                    value={data.hire_date}
                                    onChange={(e) => setData('hire_date', e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                            </div>

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
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* User Account Section */}
                    <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <label className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={createUserAccount}
                                    onChange={(e) => {
                                        setCreateUserAccount(e.target.checked);
                                        setData('create_user_account', e.target.checked);
                                    }}
                                    className="w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500"
                                />
                                <span className="text-lg font-semibold text-gray-900">Create System User Account</span>
                            </label>
                            <p className="text-sm text-gray-500 mt-1 ml-8">
                                Enable this to create login credentials for this employee
                            </p>
                        </div>

                        {createUserAccount && (
                            <div className="p-6 space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Username
                                        </label>
                                        <input
                                            type="text"
                                            value={data.employee_number}
                                            readOnly
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed"
                                        />
                                        <p className="mt-1 text-xs text-gray-500">Username defaults to Employee Number</p>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Default Password
                                        </label>
                                        <input
                                            type="text"
                                            value={data.last_name ? data.last_name.toLowerCase() : ''}
                                            readOnly
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed"
                                        />
                                        <p className="mt-1 text-xs text-gray-500">
                                            Default password is the employee's last name in lowercase.
                                            They will be required to change it on first login.
                                        </p>
                                    </div>
                                </div>

                                {/* Role Selection */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Assign Roles
                                    </label>
                                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                                        {roles.map((role) => (
                                            <label
                                                key={role.id}
                                                className={`flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors ${data.role_ids.includes(role.id)
                                                    ? 'border-orange-500 bg-orange-50'
                                                    : 'border-gray-200 hover:bg-gray-50'
                                                    }`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={data.role_ids.includes(role.id)}
                                                    onChange={() => handleRoleChange(role.id)}
                                                    className="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500"
                                                />
                                                <span className="text-sm font-medium text-gray-700">{role.name}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Form Actions */}
                    <div className="flex justify-end gap-3">
                        <Link
                            href={route('admin.employees.index')}
                            className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50"
                        >
                            {processing ? 'Creating...' : 'Create Employee'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
