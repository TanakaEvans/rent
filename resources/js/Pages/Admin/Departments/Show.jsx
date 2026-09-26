import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

function Detail({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900">{children || '—'}</dd>
        </div>
    );
}

export default function DepartmentShow({ department }) {
    return (
        <AdminLayout title="Department Details">
            <Head title={`Department - ${department.name}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Link href={route('admin.departments.index')} className="text-orange-600 hover:text-orange-700 flex items-center gap-1 mb-2">
                            ← Back to Departments
                        </Link>
                        <h2 className="text-2xl font-bold text-gray-900">{department.name}</h2>
                        <p className="text-sm text-gray-500 font-mono">{department.code}</p>
                    </div>
                    <Link href={route('admin.departments.edit', department.id)} className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                        Edit Department
                    </Link>
                </div>

                <div className="bg-white rounded-xl shadow-sm p-6">
                    <dl className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Detail label="Branch">
                            {department.branch ? (
                                <Link href={route('admin.branches.show', department.branch.id)} className="hover:text-orange-600">
                                    {department.branch.name}
                                </Link>
                            ) : null}
                        </Detail>
                        <Detail label="Company">{department.branch?.company?.name}</Detail>
                        <Detail label="Status">{department.status === 'active' ? 'Active' : 'Inactive'}</Detail>
                        <Detail label="Department Head">{department.head ? `${department.head.first_name} ${department.head.last_name}` : null}</Detail>
                        <div className="md:col-span-2">
                            <Detail label="Description">{department.description}</Detail>
                        </div>
                    </dl>
                </div>

                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 className="text-lg font-semibold text-gray-900">Employees ({department.employees.length})</h3>
                    </div>
                    {department.employees.length === 0 ? (
                        <p className="p-6 text-sm text-gray-500">No employees in this department yet.</p>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {department.employees.map((employee) => (
                                <li key={employee.id} className="px-6 py-3 flex items-center justify-between">
                                    <Link href={route('admin.employees.show', employee.id)} className="text-sm font-medium text-gray-900 hover:text-orange-600">
                                        {employee.first_name} {employee.last_name}
                                    </Link>
                                    <span className="text-xs text-gray-500">{employee.job_title}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
