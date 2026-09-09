import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function SectionsIndex({ auth, sections, departments, employees }) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingSection, setEditingSection] = useState(null);

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        name: '',
        department_id: '',
        head_id: '',
        description: ''
    });

    const openCreateModal = () => {
        setEditingSection(null);
        reset();
        setIsModalOpen(true);
    };

    const openEditModal = (section) => {
        setEditingSection(section);
        setData({
            name: section.name,
            department_id: section.department_id,
            head_id: section.head_id || '',
            description: section.description || ''
        });
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        reset();
        setEditingSection(null);
    };

    const submit = (e) => {
        e.preventDefault();
        if (editingSection) {
            put(route('admin.sections.update', editingSection.id), {
                onSuccess: () => closeModal()
            });
        } else {
            post(route('admin.sections.store'), {
                onSuccess: () => closeModal()
            });
        }
    };

    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this section?')) {
            destroy(route('admin.sections.destroy', id));
        }
    };

    return (
        <AdminLayout title="Sections Management" auth={auth}>
            <Head title="Sections Management" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Sections Management</h1>
                        <p className="text-gray-600">Manage organizational sections within departments.</p>
                    </div>
                    <button
                        onClick={openCreateModal}
                        className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2"
                    >
                        + Add New Section
                    </button>
                </div>

                <div className="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead className="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4 font-semibold text-gray-900">Section Name</th>
                                    <th className="px-6 py-4 font-semibold text-gray-900">Department</th>
                                    <th className="px-6 py-4 font-semibold text-gray-900">Head of Section</th>
                                    <th className="px-6 py-4 font-semibold text-gray-900">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {sections.data.map((section) => (
                                    <tr key={section.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4 font-medium text-gray-900">
                                            {section.name}
                                            {section.description && (
                                                <div className="text-xs text-gray-500 font-normal">{section.description}</div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            {section.department?.name || <span className="text-red-500 text-xs">Unassigned</span>}
                                        </td>
                                        <td className="px-6 py-4">
                                            {section.head ? (
                                                <div className="flex items-center gap-2">
                                                    <div className="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs">
                                                        {section.head.first_name.charAt(0)}
                                                    </div>
                                                    <span className="text-sm text-gray-700">{section.head.first_name} {section.head.last_name}</span>
                                                </div>
                                            ) : (
                                                <span className="text-gray-400 text-sm">--</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                <button
                                                    onClick={() => openEditModal(section)}
                                                    className="text-blue-600 hover:bg-blue-50 p-2 rounded-lg transition-colors"
                                                    title="Edit"
                                                >
                                                    ✏️
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(section.id)}
                                                    className="text-red-600 hover:bg-red-50 p-2 rounded-lg transition-colors"
                                                    title="Delete"
                                                >
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {sections.data.length === 0 && (
                                    <tr>
                                        <td colSpan="4" className="px-6 py-8 text-center text-gray-500">
                                            No sections found. Create one to get started.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    {sections.links && sections.links.length > 3 && (
                        <div className="p-4 border-t border-gray-100 flex justify-center">
                            <div className="flex gap-1">
                                {sections.links.map((link, i) => (
                                    <button
                                        key={i}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                        className={`px-3 py-1 rounded border ${link.active
                                            ? 'bg-blue-600 text-white border-blue-600'
                                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'} 
                                        ${!link.url && 'opacity-50 cursor-not-allowed'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        disabled={!link.url}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                            <h3 className="text-lg font-semibold text-gray-900">
                                {editingSection ? 'Edit Section' : 'Add New Section'}
                            </h3>
                            <button onClick={closeModal} className="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        <form onSubmit={submit} className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Section Name *</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                    required
                                />
                                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Department *</label>
                                <select
                                    value={data.department_id}
                                    onChange={e => setData('department_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                    required
                                >
                                    <option value="">Select Department</option>
                                    {departments.map(dept => (
                                        <option key={dept.id} value={dept.id}>{dept.name}</option>
                                    ))}
                                </select>
                                {errors.department_id && <p className="text-red-500 text-xs mt-1">{errors.department_id}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Head of Section (Optional)</label>
                                <select
                                    value={data.head_id}
                                    onChange={e => setData('head_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                >
                                    <option value="">Select Head</option>
                                    {employees.map(emp => (
                                        <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name}</option>
                                    ))}
                                </select>
                                {errors.head_id && <p className="text-red-500 text-xs mt-1">{errors.head_id}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea
                                    value={data.description}
                                    onChange={e => setData('description', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                    rows="3"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : 'Save Section'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
