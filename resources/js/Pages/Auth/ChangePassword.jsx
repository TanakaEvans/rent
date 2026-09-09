import { Head, useForm } from '@inertiajs/react';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { useState } from 'react';

export default function ChangePassword({ flash }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            onSuccess: () => reset(),
        });
    };

    const doPasswordsMatch = data.password && data.password === data.password_confirmation;

    return (
        <div className="min-h-screen flex bg-gray-50 items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <Head title="Change Password" />

            <div className="max-w-lg w-full space-y-8 bg-white p-10 rounded-xl shadow-lg">
                <div>
                    <div className="mx-auto w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <span className="text-4xl">🔐</span>
                    </div>
                    <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
                        Change Password
                    </h2>
                    <p className="mt-2 text-center text-sm text-gray-600">
                        {flash?.warning || "You must update your password to continue."}
                    </p>
                </div>

                <form className="mt-8 space-y-6" onSubmit={submit}>
                    <div className="space-y-6">
                        {/* Current Password */}
                        <div className="relative">
                            <InputLabel htmlFor="current_password" value="Current Password" className="text-base" />
                            <div className="relative mt-1">
                                <TextInput
                                    id="current_password"
                                    type={showCurrentPassword ? "text" : "password"}
                                    value={data.current_password}
                                    onChange={(e) => setData('current_password', e.target.value)}
                                    className="block w-full text-lg p-3 pr-10 border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                    required
                                />
                                <button
                                    type="button"
                                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                                    onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                >
                                    {showCurrentPassword ? '🙈' : '👁️'}
                                </button>
                            </div>
                            <InputError message={errors.current_password} className="mt-2" />
                        </div>

                        {/* New Password */}
                        <div className="relative">
                            <InputLabel htmlFor="password" value="New Password" className="text-base" />
                            <div className="relative mt-1">
                                <TextInput
                                    id="password"
                                    type={showNewPassword ? "text" : "password"}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="block w-full text-lg p-3 pr-10 border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                    required
                                />
                                <button
                                    type="button"
                                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                                    onClick={() => setShowNewPassword(!showNewPassword)}
                                >
                                    {showNewPassword ? '🙈' : '👁️'}
                                </button>
                            </div>
                            <p className="text-xs text-gray-500 mt-1">
                                Must be at least 8 characters, include uppercase, lowercase, numbers, and symbols.
                            </p>
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        {/* Confirm Password */}
                        <div className="relative">
                            <InputLabel htmlFor="password_confirmation" value="Confirm New Password" className="text-base" />
                            <div className="relative mt-1">
                                <TextInput
                                    id="password_confirmation"
                                    type={showConfirmPassword ? "text" : "password"}
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    className={`block w-full text-lg p-3 pr-10 border rounded-lg focus:ring-blue-500 focus:border-blue-500 ${data.password_confirmation && (doPasswordsMatch ? 'border-green-300 focus:border-green-500 focus:ring-green-500' : 'border-red-300 focus:border-red-500 focus:ring-red-500')
                                        }`}
                                    required
                                />
                                <button
                                    type="button"
                                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                >
                                    {showConfirmPassword ? '🙈' : '👁️'}
                                </button>
                            </div>
                            {data.password_confirmation && (
                                <p className={`text-sm mt-1 flex items-center gap-1 ${doPasswordsMatch ? 'text-green-600' : 'text-red-500'}`}>
                                    {doPasswordsMatch ? '✅ Passwords match' : '❌ Passwords do not match'}
                                </p>
                            )}
                            <InputError message={errors.password_confirmation} className="mt-2" />
                        </div>
                    </div>

                    <div>
                        <Button
                            type="submit"
                            className="w-full flex justify-center py-4 text-lg font-bold bg-blue-600 hover:bg-blue-700 transition duration-150 ease-in-out"
                            processing={processing}
                        >
                            Update Password
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
