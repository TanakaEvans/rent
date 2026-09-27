import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';

export default function Register({ errors, defaultRole = 'tenant' }) {
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: defaultRole === 'owner' ? 'owner' : 'tenant',
        terms: false,
    });

    const isOwner = data.role === 'owner';

    const [showPassword, setShowPassword] = useState(false);
    const [focusedField, setFocusedField] = useState(null);

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const inputClass = (field) =>
        `h-11 text-sm border rounded-lg transition-all duration-200 ${
            focusedField === field
                ? 'border-emerald-400 ring-2 ring-emerald-100'
                : 'border-slate-300'
        }`;

    return (
        <div className="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
            <Head title="ZimRent - Create account" />

            {/* Full-screen background */}
            <div className="absolute inset-0 bg-slate-900">
                <div className="absolute inset-0 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900" />
                <div className="absolute inset-0 opacity-[0.04]"
                    style={{
                        backgroundImage: 'radial-gradient(circle at 25% 50%, white 1px, transparent 1px)',
                        backgroundSize: '40px 40px',
                    }}
                />
                <div className="absolute inset-0 bg-gradient-to-r from-emerald-600/10 to-transparent" />
            </div>

            <div className="relative w-full max-w-6xl grid lg:grid-cols-5 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl shadow-slate-900/30 min-h-[680px] overflow-hidden">
                {/* LEFT PANEL: Brand / Hero */}
                <div className="lg:col-span-2 bg-gradient-to-br from-slate-900/95 via-slate-800/90 to-slate-900/95 p-10 lg:p-12 flex flex-col relative overflow-hidden hidden lg:flex">
                    <svg className="absolute top-0 right-0 w-72 h-72 text-white/5" viewBox="0 0 200 200" fill="none">
                        <path d="M0 200L200 0H200V200H0Z" fill="currentColor" />
                        <path d="M50 200L200 50V200H50Z" fill="currentColor" opacity="0.5" />
                    </svg>
                    <svg className="absolute bottom-0 left-0 w-96 h-96 text-white/[0.03]" viewBox="0 0 200 200" fill="none">
                        <circle cx="100" cy="100" r="100" fill="currentColor" />
                        <circle cx="100" cy="100" r="60" fill="white" opacity="0.3" />
                    </svg>

                    <div className="relative z-10 flex flex-col h-full">
                        <div className="mb-12 flex items-center gap-3">
                            <div className="w-10 h-10 bg-cyan-600 rounded-lg flex items-center justify-center text-xl shadow-lg shadow-cyan-900/30">
                                <svg className="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10" /></svg>
                            </div>
                            <h2 className="text-2xl font-bold text-white tracking-tight">ZimRent</h2>
                        </div>

                        <div className="flex-1 flex flex-col justify-center">
                            <h1 className="text-3xl font-bold text-white leading-tight tracking-tight mb-4">
                                {isOwner
                                    ? 'List your property. Rent it out directly.'
                                    : 'Tenants search, save & apply for free.'}
                            </h1>
                            <p className="text-sm text-slate-400 leading-relaxed max-w-sm">
                                {isOwner
                                    ? 'Create an owner account in under a minute, list your property and deal with tenants directly — no agent, no commission.'
                                    : 'Create a tenant account in under a minute and start browsing verified homes let directly by their owners — no agent fees, no commissions.'}
                            </p>

                            <div className="w-12 h-0.5 bg-emerald-500 rounded-full my-8" />

                            <div className="space-y-4">
                                <div className="flex items-start gap-3">
                                    <div className="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg className="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span className="text-sm text-slate-300">Search &amp; filter the full Harare marketplace</span>
                                </div>
                                <div className="flex items-start gap-3">
                                    <div className="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg className="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span className="text-sm text-slate-300">Enquire, book viewings &amp; apply to rentals</span>
                                </div>
                                <div className="flex items-start gap-3">
                                    <div className="w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg className="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <span className="text-sm text-slate-300">Save favourites &amp; track your applications</span>
                                </div>
                            </div>
                        </div>

                        <div className="text-[11px] text-slate-600 tracking-wide">
                            ZimRent Property Platform
                        </div>
                    </div>
                </div>

                {/* RIGHT PANEL: Register Form */}
                <div className="lg:col-span-3 p-8 lg:p-12 xl:p-14 flex flex-col justify-center">
                    <div className="max-w-sm mx-auto w-full">
                        <div className="mb-6">
                            <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Create your account</h2>
                            <p className="text-sm text-slate-500 mt-1.5">
                                {isOwner
                                    ? 'List your property and manage tenants directly — no agent commission.'
                                    : 'Free to join. Tenants never pay listing or agency fees.'}
                            </p>
                        </div>

                        {/* Account type toggle */}
                        <div className="mb-6 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Account type">
                            {[
                                { key: 'tenant', label: 'Rent a home', hint: "I'm looking" },
                                { key: 'owner', label: 'List property', hint: "I'm an owner" },
                            ].map((option) => (
                                <button
                                    key={option.key}
                                    type="button"
                                    role="tab"
                                    aria-selected={data.role === option.key}
                                    onClick={() => setData('role', option.key)}
                                    className={`flex flex-col items-center rounded-lg px-3 py-2 text-sm font-semibold transition-all ${
                                        data.role === option.key
                                            ? 'bg-white text-emerald-700 shadow-sm'
                                            : 'text-slate-500 hover:text-slate-700'
                                    }`}
                                >
                                    {option.label}
                                    <span className="text-[11px] font-normal text-slate-400">{option.hint}</span>
                                </button>
                            ))}
                        </div>

                        {Object.keys(errors).length > 0 && (
                            <div className="mb-6 p-3.5 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3 text-sm text-red-700">
                                <svg className="w-4 h-4 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <strong>Signup failed:</strong> {Object.values(errors)[0]}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="name" className="text-sm font-medium text-slate-700">Full name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    onFocus={() => setFocusedField('name')}
                                    onBlur={() => setFocusedField(null)}
                                    className={inputClass('name')}
                                    placeholder="Tendai Moyo"
                                    required
                                    autoComplete="name"
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="email" className="text-sm font-medium text-slate-700">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    onFocus={() => setFocusedField('email')}
                                    onBlur={() => setFocusedField(null)}
                                    className={inputClass('email')}
                                    placeholder="you@example.com"
                                    required
                                    autoComplete="email"
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="password" className="text-sm font-medium text-slate-700">Password</Label>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        onFocus={() => setFocusedField('password')}
                                        onBlur={() => setFocusedField(null)}
                                        className={`${inputClass('password')} pr-12`}
                                        placeholder="Min. 8 characters"
                                        required
                                        autoComplete="new-password"
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

                            <div className="space-y-1.5">
                                <Label htmlFor="password_confirmation" className="text-sm font-medium text-slate-700">Confirm password</Label>
                                <Input
                                    id="password_confirmation"
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    onFocus={() => setFocusedField('password_confirmation')}
                                    onBlur={() => setFocusedField(null)}
                                    className={inputClass('password_confirmation')}
                                    placeholder="Repeat your password"
                                    required
                                    autoComplete="new-password"
                                />
                            </div>

                            <div className="flex items-start gap-2.5 pt-1">
                                <Checkbox
                                    id="terms"
                                    checked={data.terms}
                                    onCheckedChange={(checked) => setData('terms', checked === true)}
                                    className="mt-0.5 border-slate-300 data-[state=checked]:bg-emerald-600 data-[state=checked]:border-emerald-600"
                                />
                                <Label htmlFor="terms" className="text-sm text-slate-500 cursor-pointer select-none leading-relaxed">
                                    I agree to the ZimRent Terms of Service and Privacy Policy.
                                </Label>
                            </div>
                            {errors.terms && (
                                <p className="text-xs text-red-600 -mt-3">{errors.terms}</p>
                            )}

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full h-11 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold text-sm rounded-lg shadow-sm hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? (
                                    <span className="flex items-center gap-2">
                                        <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Creating account...
                                    </span>
                                ) : (
                                    <span>{isOwner ? 'Create owner account' : 'Create free account'}</span>
                                )}
                            </Button>
                        </form>

                        <div className="mt-6 flex items-center justify-between">
                            <p className="text-sm text-slate-500">
                                Already have an account?{' '}
                                <Link href={route('login')} className="font-medium text-emerald-600 hover:text-emerald-700 transition-colors">
                                    Sign in
                                </Link>
                            </p>
                            <Link href={route('home')} className="text-sm font-medium text-slate-400 hover:text-slate-600 transition-colors">
                                Browse properties
                            </Link>
                        </div>

                        <p className="mt-8 text-xs text-slate-400 text-center">
                            &copy; {new Date().getFullYear()} ZimRent Property Platform. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}