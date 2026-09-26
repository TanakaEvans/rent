import { Link } from '@inertiajs/react';
import { ArrowRight, Building2, KeyRound, ShieldCheck } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import { RichText } from '@/Components/Help/GuideViewer';

const icons = { tenant: KeyRound, owner: Building2 };

const safety = [
    'Always view a property in person before paying anything.',
    'Never send money or ID documents to someone you have not met through ZimRent.',
    'Use the **Report listing** button on any listing that looks suspicious.',
];

export default function HelpIndex({ guides = [] }) {
    return (
        <PublicLayout title="Help Center">
            <section className="bg-brand text-white">
                <div className="mx-auto max-w-7xl px-4 pb-16 pt-14 sm:px-6 sm:pt-20 lg:px-8">
                    <p className="text-sm font-semibold text-pitch">Help Center</p>
                    <h1 className="mt-3 max-w-3xl text-balance text-4xl font-semibold tracking-tight sm:text-6xl">How can we help you today?</h1>
                    <p className="mt-5 max-w-2xl text-lg leading-8 text-white/70">
                        Step-by-step guides for finding a home or letting one out on ZimRent — from your first search to signing a lease and paying rent.
                    </p>
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div className="grid gap-6 md:grid-cols-2">
                    {guides.map((guide) => {
                        const Icon = icons[guide.key] || KeyRound;
                        return (
                            <Link
                                key={guide.key}
                                href={route('help.show', guide.key)}
                                className="group flex flex-col rounded-3xl border border-slate-200 bg-white p-8 transition hover:border-brand hover:shadow-[0_20px_40px_-24px_rgba(55,0,60,0.35)]"
                            >
                                <Icon className="h-8 w-8 text-brand" strokeWidth={1.8} />
                                <h2 className="mt-6 text-2xl font-semibold tracking-tight text-slate-900">{guide.title}</h2>
                                <p className="mt-2 text-[15px] leading-7 text-slate-600">{guide.tagline}</p>
                                <ul className="mt-5 flex flex-wrap gap-2">
                                    {guide.topics.map((topic) => (
                                        <li key={topic} className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{topic}</li>
                                    ))}
                                </ul>
                                <span className="mt-8 inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                                    Open the guide · {guide.articles} topics <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />
                                </span>
                            </Link>
                        );
                    })}
                </div>

                <div className="mt-10 flex flex-col gap-4 rounded-3xl bg-slate-100 p-8 sm:flex-row">
                    <ShieldCheck className="h-8 w-8 shrink-0 text-brand" strokeWidth={1.8} />
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight text-slate-900">Stay safe when renting</h2>
                        <ul className="mt-3 space-y-2 text-[15px] leading-7 text-slate-600">
                            {safety.map((tip) => (
                                <li key={tip}><RichText text={tip} /></li>
                            ))}
                        </ul>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
