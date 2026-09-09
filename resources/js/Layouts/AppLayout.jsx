import { Head, Link, router } from '@inertiajs/react';

export default function AppLayout({ children, title = 'Dashboard', auth }) {
    const logout = () => {
        router.post(route('logout'));
    };

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-gray-50">
                {/* Simple Header */}
                <header className="bg-white border-b border-gray-200 px-6 py-4">
                    <div className="max-w-7xl mx-auto flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-2xl">
                                ⚙️
                            </div>
                            <span className="text-xl font-bold text-gray-900">System Management</span>
                        </div>

                        <div className="flex items-center gap-4">
                            <div className="text-sm text-gray-600">
                                Welcome, <span className="font-semibold">{auth?.user?.name}</span>
                            </div>
                            <button
                                onClick={logout}
                                className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                </header>

                {/* Main Content */}
                <main className="max-w-7xl mx-auto p-6">
                    {children}
                </main>
            </div>
        </>
    );
}
