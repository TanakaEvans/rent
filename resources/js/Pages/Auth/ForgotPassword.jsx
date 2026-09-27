import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ForgotPassword({ status, errors = {} }) {
    const { data, setData, post, processing } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <div className="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
            <Head title="ZimRent - Forgot password" />

            <div className="absolute inset-0 bg-slate-900">
                <div className="absolute inset-0 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900" />
            </div>

            <div className="relative w-full max-w-md bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl shadow-slate-900/30 p-8 sm:p-10">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Reset your password</h1>
                    <p className="text-sm text-slate-500 mt-1.5">
                        Enter your account email and we'll send you a link to set a new password.
                    </p>
                </div>

                {status && (
                    <div className="mb-6 p-3.5 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800">
                        {status}
                    </div>
                )}

                {Object.keys(errors).length > 0 && (
                    <div className="mb-6 p-3.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        {Object.values(errors)[0]}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="email" className="text-sm font-medium text-slate-700">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="h-11 text-sm border border-slate-300 rounded-lg"
                            placeholder="you@example.com"
                            required
                            autoComplete="email"
                            autoFocus
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full h-11 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold text-sm rounded-lg transition-all disabled:opacity-50"
                    >
                        {processing ? 'Sending…' : 'Email me a reset link'}
                    </Button>
                </form>

                <p className="mt-6 text-sm text-slate-500">
                    Remembered it?{' '}
                    <Link href={route('login')} className="font-medium text-emerald-600 hover:text-emerald-700 transition-colors">
                        Back to sign in
                    </Link>
                </p>
            </div>
        </div>
    );
}
