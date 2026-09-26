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

export default function BranchShow({ branch }) {
    return (
        <AdminLayout title="Branch Details">
            <Head title={`Branch - ${branch.name}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Link href={route('admin.branches.index')} className="text-orange-600 hover:text-orange-700 flex items-center gap-1 mb-2">
                            ← Back to Branches
                        </Link>
                        <h2 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            {branch.name}
                            {branch.is_main_branch && <span className="px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800 rounded-full">Main</span>}
                        </h2>
                        <p className="text-sm text-gray-500 font-mono">{branch.code}</p>
                    </div>
                    <Link href={route('admin.branches.edit', branch.id)} className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                        Edit Branch
                    </Link>
                </div>

                <div className="bg-white rounded-xl shadow-sm p-6">
                    <dl className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Detail label="Company">{branch.company?.name}</Detail>
                        <Detail label="Status">{branch.status === 'active' ? 'Active' : 'Inactive'}</Detail>
                        <Detail label="Branch Head">{branch.head ? `${branch.head.first_name} ${branch.head.last_name}` : null}</Detail>
                        <Detail label="Email">{branch.email}</Detail>
                        <Detail label="Phone">{branch.phone}</Detail>
                        <Detail label="City">{branch.city}</Detail>
                        <div className="md:col-span-3">
                            <Detail label="Address">{branch.address}</Detail>
                        </div>
                    </dl>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 className="text-lg font-semibold text-gray-900">Departments ({branch.departments.length})</h3>
                        </div>
                        {branch.departments.length === 0 ? (
                            <p className="p-6 text-sm text-gray-500">No departments in this branch yet.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {branch.departments.map((department) => (
                                    <li key={department.id} className="px-6 py-3 flex items-center justify-between">
                                        <Link href={route('admin.departments.show', department.id)} className="text-sm font-medium text-gray-900 hover:text-orange-600">
                                            {department.name}
                                        </Link>
                                        <span className="text-xs text-gray-500 font-mono">{department.code}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 className="text-lg font-semibold text-gray-900">Employees ({branch.employees.length})</h3>
                        </div>
                        {branch.employees.length === 0 ? (
                            <p className="p-6 text-sm text-gray-500">No employees in this branch yet.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {branch.employees.map((employee) => (
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
            </div>
        </AdminLayout>
    );
}
