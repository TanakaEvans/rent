import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';

export default function Login({ status, errors }) {
    const { data, setData, post, processing, reset } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [focusedField, setFocusedField] = useState(null);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const fillDemo = (email) => {
        setData({ ...data, login: email, password: 'password123' });
    };

    const demoAccounts = [
        {
            label: 'Property Owner',
            sub: 'owner@dzimba.local',
            icon: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
            gradient: 'from-cyan-500 to-blue-600',
        },
        {
            label: 'Tenant',
            sub: 'tenant@dzimba.local',
            icon: 'M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16l-3-2-2 2-3-2-2 2-3-2z',
            gradient: 'from-emerald-500 to-teal-600',
        },
        {
            label: 'Admin',
            sub: 'staff@dzimba.local',
            icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            gradient: 'from-slate-500 to-slate-700',
        },
    ];

    return (
        <div className="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
            <Head title="Dzimba - Login" />

            {/* Full-screen background */}
            <div className="absolute inset-0 bg-slate-900">
                <div className="absolute inset-0 bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900" />
                <div className="absolute inset-0 opacity-[0.04]"
                    style={{
                        backgroundImage: 'radial-gradient(circle at 25% 50%, white 1px, transparent 1px)',
                        backgroundSize: '40px 40px',
                    }}
                />
                <div className="absolute inset-0 bg-gradient-to-r from-blue-600/10 to-transparent" />
            </div>

            <div className="relative w-full max-w-6xl grid lg:grid-cols-5 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl shadow-slate-900/30 min-h-[680px] overflow-hidden">
                {/* LEFT PANEL: Brand / Hero */}
                <div className="lg:col-span-2 bg-gradient-to-br from-slate-900/95 via-slate-800/90 to-slate-900/95 p-10 lg:p-12 flex flex-col relative overflow-hidden hidden lg:flex">
                    {/* Abstract geometric decoration */}
                    <svg className="absolute top-0 right-0 w-72 h-72 text-white/5" viewBox="0 0 200 200" fill="none">
                        <path d="M0 200L200 0H200V200H0Z" fill="currentColor" />
                        <path d="M50 200L200 50V200H50Z" fill="currentColor" opacity="0.5" />
                    </svg>
                    <svg className="absolute bottom-0 left-0 w-96 h-96 text-white/[0.03]" viewBox="0 0 200 200" fill="none">
                        <circle cx="100" cy="100" r="100" fill="currentColor" />
                        <circle cx="100" cy="100" r="60" fill="white" opacity="0.3" />
                    </svg>

                    <div className="relative z-10 flex flex-col h-full">
                        {/* Logo */}
                        <div className="mb-12 flex items-center gap-3">
                            <div className="w-10 h-10 bg-cyan-600 rounded-lg flex items-center justify-center text-xl shadow-lg shadow-cyan-900/30">
                                <svg className="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10" /></svg>
                            </div>
                            <h2 className="text-2xl font-bold text-white tracking-tight">Dzimba</h2>
                        </div>

                        {/* Title + description */}
                        <div className="flex-1 flex flex-col justify-center">
                            <h1 className="text-3xl font-bold text-white leading-tight tracking-tight mb-4">
                                Rent direct.<br />No agents.<br />No commissions.
                            </h1>
                            <p className="text-sm text-slate-400 leading-relaxed max-w-sm">
                                Dzimba is the direct property rental platform. Property owners list their homes
                                and tenants find and apply for them — with no traditional agent fees, just
                                affordable subscriptions.
                            </p>

                            {/* Divider */}
                            <div className="w-12 h-0.5 bg-blue-500 rounded-full my-8" />

                            {/* Value props */}
                            <div className="space-y-4">
                                <div className="flex items-start gap-3">
                                    <div className="w-5 h-5 rounded-full bg-blue-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg className="w-3 h-3 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span className="text-sm text-slate-300">Owners upload &amp; manage their own properties</span>
                                </div>
                                <div className="flex items-start gap-3">
                                    <div className="w-5 h-5 rounded-full bg-blue-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg className="w-3 h-3 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span className="text-sm text-slate-300">Tenants search, save &amp; apply for free</span>
                                </div>
                                <div className="flex items-start gap-3">
                                    <div className="w-5 h-5 rounded-full bg-blue-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg className="w-3 h-3 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span className="text-sm text-slate-300">Verified property &amp; owner badges for trust</span>
                                </div>
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="text-[11px] text-slate-600 tracking-wide">
                            Dzimba Property Platform
                        </div>
                    </div>
                </div>

                {/* RIGHT PANEL: Login Form */}
                <div className="lg:col-span-3 p-8 lg:p-12 xl:p-14 flex flex-col justify-center">
                    <div className="max-w-sm mx-auto w-full">
                        {/* Form header */}
                        <div className="mb-10">
                            <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Welcome back</h2>
                            <p className="text-sm text-slate-500 mt-1.5">Sign in to your Dzimba account to continue.</p>
                        </div>

                        {/* Status messages */}
                        {status && (
                            <div className="mb-6 p-3.5 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-3 text-sm text-emerald-700">
                                <svg className="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {status}
                            </div>
                        )}

                        {Object.keys(errors).length > 0 && (
                            <div className="mb-6 p-3.5 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3 text-sm text-red-700">
                                <svg className="w-4 h-4 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <strong>Login failed:</strong> {Object.values(errors)[0]}
                            </div>
                        )}

                        {/* Form */}
                        <form onSubmit={submit} className="space-y-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="login" className="text-sm font-medium text-slate-700">
                                    Email or Username
                                </Label>
                                <Input
                                    id="login"
                                    type="text"
                                    value={data.login}
                                    onChange={(e) => setData('login', e.target.value)}
                                    onFocus={() => setFocusedField('login')}
                                    onBlur={() => setFocusedField(null)}
                                    className={`h-11 text-sm border rounded-lg transition-all duration-200 ${
                                        focusedField === 'login'
                                            ? 'border-blue-400 ring-2 ring-blue-100'
                                            : 'border-slate-300'
                                    }`}
                                    placeholder="you@example.com"
                                    required
                                    autoFocus
                                    autoComplete="username"
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="password" className="text-sm font-medium text-slate-700">
                                    Password
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        onFocus={() => setFocusedField('password')}
                                        onBlur={() => setFocusedField(null)}
                                        className={`h-11 text-sm border rounded-lg pr-12 transition-all duration-200 ${
                                            focusedField === 'password'
                                                ? 'border-blue-400 ring-2 ring-blue-100'
                                                : 'border-slate-300'
                                        }`}
                                        placeholder="Enter your password"
                                        required
                                        autoComplete="current-password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            {showPassword ? (
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            ) : (
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            )}
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div className="flex items-center justify-between pt-1">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="remember"
                                        checked={data.remember}
                                        onCheckedChange={(checked) => setData('remember', checked)}
                                        className="border-slate-300 data-[state=checked]:bg-blue-600 data-[state=checked]:border-blue-600"
                                    />
                                    <Label htmlFor="remember" className="text-sm text-slate-500 cursor-pointer select-none">
                                        Remember me
                                    </Label>
                                </div>
                                <a href="#" className="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                                    Forgot password?
                                </a>
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full h-11 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold text-sm rounded-lg shadow-sm hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? (
                                    <span className="flex items-center gap-2">
                                        <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Signing in...
                                    </span>
                                ) : (
                                    <span>Sign in</span>
                                )}
                            </Button>
                        </form>

                        {/* Demo section */}
                        <Card className="mt-8 border border-slate-200 bg-slate-50 rounded-xl shadow-none">
                            <CardContent className="p-4 space-y-2">
                                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                                    <span className="inline-block w-1.5 h-1.5 bg-emerald-400 rounded-full mr-1.5 align-middle" />
                                    Demo access (password: password123)
                                </p>
                                {demoAccounts.map((account) => (
                                    <div
                                        key={account.label}
                                        className="flex items-center justify-between bg-white rounded-lg px-4 py-2.5 border border-slate-200 cursor-pointer hover:border-blue-200 hover:shadow-sm transition-all duration-200 group"
                                        onClick={() => fillDemo(account.sub)}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className={`w-8 h-8 rounded-full bg-gradient-to-br ${account.gradient} flex items-center justify-center`}>
                                                <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={account.icon} /></svg>
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-slate-800">{account.label}</p>
                                                <p className="text-xs text-slate-500">{account.sub}</p>
                                            </div>
                                        </div>
                                        <svg className="w-4 h-4 text-slate-300 group-hover:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        {/* Footer */}
                        <p className="mt-8 text-xs text-slate-400 text-center">
                            &copy; {new Date().getFullYear()} Dzimba Property Platform. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}