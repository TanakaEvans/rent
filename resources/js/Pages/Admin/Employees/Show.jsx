import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const labelFor = (value) => (value ? String(value).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '—');
const dateFor = (value) => (value ? new Date(value).toLocaleDateString() : '—');

function Detail({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900">{children || '—'}</dd>
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
            </div>
            <div className="p-6">{children}</div>
        </div>
    );
}

export default function EmployeeShow({ employee }) {
    const user = employee.user;

    return (
        <AdminLayout title="Employee Details">
            <Head title={`Employee - ${employee.first_name} ${employee.last_name}`} />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Link href={route('admin.employees.index')} className="text-orange-600 hover:text-orange-700 flex items-center gap-1 mb-2">
                            ← Back to Employees
                        </Link>
                        <h2 className="text-2xl font-bold text-gray-900">
                            {employee.first_name} {employee.middle_name} {employee.last_name}
                        </h2>
                        <p className="text-sm text-gray-500 font-mono">{employee.employee_number}</p>
                    </div>
                    <Link
                        href={route('admin.employees.edit', employee.id)}
                        className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors"
                    >
                        Edit Employee
                    </Link>
                </div>

                <Card title="Personal Information">
                    <dl className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Detail label="Gender">{labelFor(employee.gender)}</Detail>
                        <Detail label="Date of Birth">{dateFor(employee.date_of_birth)}</Detail>
                        <Detail label="National ID">{employee.national_id}</Detail>
                        <Detail label="Email">{employee.email}</Detail>
                        <Detail label="Phone">{employee.phone}</Detail>
                        <Detail label="Alternative Phone">{employee.alt_phone}</Detail>
                        <Detail label="City">{employee.city}</Detail>
                        <div className="md:col-span-2">
                            <Detail label="Address">{employee.address}</Detail>
                        </div>
                    </dl>
                </Card>

                <Card title="Employment Information">
                    <dl className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Detail label="Branch">{employee.branch?.name}</Detail>
                        <Detail label="Department">{employee.department?.name}</Detail>
                        <Detail label="Job Title">{employee.job_title}</Detail>
                        <Detail label="Employment Type">{labelFor(employee.employment_type)}</Detail>
                        <Detail label="Hire Date">{dateFor(employee.hire_date)}</Detail>
                        <Detail label="Status">{labelFor(employee.status)}</Detail>
                        <Detail label="Emergency Contact">{employee.emergency_contact_name}</Detail>
                        <Detail label="Emergency Phone">{employee.emergency_contact_phone}</Detail>
                    </dl>
                </Card>

                <Card title="System User Account">
                    {user ? (
                        <dl className="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <Detail label="Username"><span className="font-mono">{user.username}</span></Detail>
                            <Detail label="Email">{user.email}</Detail>
                            <Detail label="Account Status">{user.status === 'active' ? 'Active' : 'Inactive'}</Detail>
                            <div className="md:col-span-3">
                                <Detail label="Roles">
                                    {user.roles?.length ? (
                                        <span className="flex flex-wrap gap-1">
                                            {user.roles.map((role) => (
                                                <span key={role.id} className="px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800 rounded-full">{role.name}</span>
                                            ))}
                                        </span>
                                    ) : 'No roles'}
                                </Detail>
                            </div>
                        </dl>
                    ) : (
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-gray-600">This employee cannot sign in yet.</p>
                            <Link
                                href={route('admin.employees.create-user', employee.id)}
                                className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                            >
                                Create User Account
                            </Link>
                        </div>
                    )}
                </Card>
            </div>
        </AdminLayout>
    );
}
