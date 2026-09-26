import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronLeft } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import GuideViewer from '@/Components/Help/GuideViewer';

export default function HelpGuide({ guide, otherGuides = [] }) {
    return (
        <PublicLayout title={guide.title}>
            <section className="border-b border-slate-200 bg-slate-100">
                <div className="mx-auto max-w-7xl px-4 pb-10 pt-8 sm:px-6 lg:px-8">
                    <Link href={route('help.index')} className="inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900">
                        <ChevronLeft className="h-4 w-4" /> Help Center
                    </Link>
                    <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">{guide.title}</h1>
                    <p className="mt-3 max-w-2xl text-lg leading-8 text-slate-600">{guide.tagline}</p>
                </div>
            </section>

            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <GuideViewer guide={guide} />

                {otherGuides.length > 0 && (
                    <div className="mt-16 border-t border-slate-200 pt-8">
                        {otherGuides.map((other) => (
                            <Link key={other.key} href={route('help.show', other.key)} className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
                                Looking for the {other.title.toLowerCase()}? <ArrowRight className="h-4 w-4" />
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
