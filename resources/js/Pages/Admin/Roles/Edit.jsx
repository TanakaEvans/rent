import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Button from '@/Components/Button';

export default function RolesEdit({ auth, role }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: role.name || '',
        description: role.description || '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('auth.roles.update', role.id));
    };

    return (
        <AdminLayout title={`Edit Role: ${role.name}`} auth={auth}>
            <Head title={`Edit Role: ${role.name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link
                        href={route('auth.roles.index')}
                        className="text-gray-600 hover:text-gray-800 flex items-center gap-2"
                    >
                        ← Back to Roles
                    </Link>
                </div>

                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Edit Role: {role.name}</h1>
                    <p className="text-gray-600">Update role information</p>
                </div>

                <div className="bg-white rounded-xl shadow-sm p-6">
                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <InputLabel htmlFor="name" value="Role Name" />
                            <TextInput
                                id="name"
                                name="name"
                                value={data.name}
                                className="mt-1 block w-full"
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Enter role name"
                                required
                            />
                            <InputError message={errors.name} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="description" value="Description (Optional)" />
                            <textarea
                                id="description"
                                name="description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Enter role description"
                                rows={4}
                                className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <InputError message={errors.description} className="mt-2" />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>
                                {processing ? 'Updating...' : 'Update Role'}
                            </Button>
                            <Link
                                href={route('auth.roles.show', role.id)}
                                className="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
