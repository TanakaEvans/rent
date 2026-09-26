import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const inputClass = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent';
const dateValue = (value) => (value ? String(value).slice(0, 10) : '');

function Field({ label, error, hint, className = '', children }) {
    return (
        <label className={`block ${className}`}>
            <span className="block text-sm font-medium text-gray-700 mb-1">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-xs text-gray-500">{hint}</span>}
            {error && <span className="mt-1 block text-sm text-red-600">{error}</span>}
        </label>
    );
}

function Section({ title, children }) {
    return (
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
            </div>
            <div className="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">{children}</div>
        </div>
    );
}

export default function EmployeeEdit({ employee, branches, departments }) {
    const hasAccount = Boolean(employee.user_id);

    const { data, setData, patch, processing, errors } = useForm({
        employee_number: employee.employee_number || '',
        first_name: employee.first_name || '',
        middle_name: employee.middle_name || '',
        last_name: employee.last_name || '',
        gender: employee.gender || '',
        date_of_birth: dateValue(employee.date_of_birth),
        national_id: employee.national_id || '',
        email: employee.email || '',
        phone: employee.phone || '',
        alt_phone: employee.alt_phone || '',
        address: employee.address || '',
        city: employee.city || '',
        branch_id: employee.branch_id || '',
        department_id: employee.department_id || '',
        job_title: employee.job_title || '',
        employment_type: employee.employment_type || 'full_time',
        hire_date: dateValue(employee.hire_date),
        status: employee.status || 'active',
        emergency_contact_name: employee.emergency_contact_name || '',
        emergency_contact_phone: employee.emergency_contact_phone || '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('admin.employees.update', employee.id));
    };

    const text = (key, props = {}) => (
        <input type="text" value={data[key]} onChange={(e) => setData(key, e.target.value)} className={inputClass} {...props} />
    );

    return (
        <AdminLayout title="Edit Employee">
            <Head title={`Edit Employee - ${employee.first_name} ${employee.last_name}`} />

            <div className="max-w-4xl mx-auto">
                <div className="mb-6">
                    <Link href={route('admin.employees.show', employee.id)} className="text-orange-600 hover:text-orange-700 flex items-center gap-1 mb-2">
                        ← Back to Employee
                    </Link>
                    <h2 className="text-2xl font-bold text-gray-900">Edit Employee</h2>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Section title="Personal Information">
                        <Field label="Employee Number *" error={errors.employee_number}>{text('employee_number', { required: true, className: `${inputClass} font-mono` })}</Field>
                        <Field label="First Name *" error={errors.first_name}>{text('first_name', { required: true })}</Field>
                        <Field label="Last Name *" error={errors.last_name}>{text('last_name', { required: true })}</Field>
                        <Field label="Middle Name" error={errors.middle_name}>{text('middle_name')}</Field>
                        <Field label="Gender" error={errors.gender}>
                            <select value={data.gender} onChange={(e) => setData('gender', e.target.value)} className={inputClass}>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </Field>
                        <Field label="Date of Birth" error={errors.date_of_birth}>
                            <input type="date" value={data.date_of_birth} onChange={(e) => setData('date_of_birth', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="National ID" error={errors.national_id}>{text('national_id')}</Field>
                        <Field
                            label={hasAccount ? 'Email *' : 'Email'}
                            error={errors.email}
                            hint={hasAccount ? 'Also used to sign in to the linked system user account.' : null}
                        >
                            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={inputClass} required={hasAccount} />
                        </Field>
                        <Field label="Phone" error={errors.phone}>{text('phone')}</Field>
                        <Field label="Alternative Phone" error={errors.alt_phone}>{text('alt_phone')}</Field>
                        <Field label="City" error={errors.city}>{text('city')}</Field>
                        <Field label="Address" error={errors.address} className="md:col-span-3">
                            <textarea value={data.address} onChange={(e) => setData('address', e.target.value)} rows={2} className={inputClass} />
                        </Field>
                    </Section>

                    <Section title="Employment Information">
                        <Field label="Branch" error={errors.branch_id}>
                            <select value={data.branch_id} onChange={(e) => setData('branch_id', e.target.value)} className={inputClass}>
                                <option value="">Select Branch</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>{branch.name}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Department" error={errors.department_id}>
                            <select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)} className={inputClass}>
                                <option value="">Select Department</option>
                                {departments.map((dept) => (
                                    <option key={dept.id} value={dept.id}>{dept.name}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Job Title" error={errors.job_title}>{text('job_title')}</Field>
                        <Field label="Employment Type *" error={errors.employment_type}>
                            <select value={data.employment_type} onChange={(e) => setData('employment_type', e.target.value)} className={inputClass} required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </Field>
                        <Field label="Hire Date" error={errors.hire_date}>
                            <input type="date" value={data.hire_date} onChange={(e) => setData('hire_date', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Status *" error={errors.status}>
                            <select value={data.status} onChange={(e) => setData('status', e.target.value)} className={inputClass} required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </Field>
                        <Field label="Emergency Contact Name" error={errors.emergency_contact_name}>{text('emergency_contact_name')}</Field>
                        <Field label="Emergency Contact Phone" error={errors.emergency_contact_phone}>{text('emergency_contact_phone')}</Field>
                    </Section>

                    <div className="flex justify-end gap-3">
                        <Link href={route('admin.employees.show', employee.id)} className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </Link>
                        <button type="submit" disabled={processing} className="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50">
                            {processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
