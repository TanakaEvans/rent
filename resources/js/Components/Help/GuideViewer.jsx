import { Fragment, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight, Info, Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';

/** Renders **bold** spans (used for exact on-screen labels) inside manual text. */
export function RichText({ text }) {
    return String(text)
        .split(/(\*\*[^*]+\*\*)/g)
        .map((part, i) => (part.startsWith('**') && part.endsWith('**')
            ? <strong key={i} className="font-semibold text-slate-900">{part.slice(2, -2)}</strong>
            : <Fragment key={i}>{part}</Fragment>));
}

const searchableText = (article) => [article.title, article.summary, ...article.steps, ...article.notes]
    .join(' ')
    .replace(/\*\*/g, '')
    .toLowerCase();

function Article({ article }) {
    return (
        <article id={article.id} className="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 sm:p-7">
            <h3 className="text-lg font-semibold tracking-tight text-slate-900">{article.title}</h3>
            {article.summary && <p className="mt-1.5 text-[15px] leading-7 text-slate-600"><RichText text={article.summary} /></p>}

            {article.steps.length > 0 && (
                <ol className="mt-5 space-y-3">
                    {article.steps.map((step, i) => (
                        <li key={i} className="flex gap-3.5">
                            <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand text-xs font-semibold text-white">{i + 1}</span>
                            <span className="pt-0.5 text-[15px] leading-6 text-slate-700"><RichText text={step} /></span>
                        </li>
                    ))}
                </ol>
            )}

            {article.notes.length > 0 && (
                <div className="mt-5 space-y-2 rounded-xl bg-slate-100 p-4">
                    {article.notes.map((note, i) => (
                        <p key={i} className="flex gap-2.5 text-sm leading-6 text-slate-600">
                            <Info className="mt-0.5 h-4 w-4 shrink-0 text-brand" />
                            <span><RichText text={note} /></span>
                        </p>
                    ))}
                </div>
            )}

            {article.links.length > 0 && (
                <div className="mt-5 flex flex-wrap gap-2">
                    {article.links.map((link) => (
                        <Link key={link.href} href={link.href} className="inline-flex h-9 items-center gap-1.5 rounded-full border border-slate-300 px-4 text-sm font-medium text-slate-900 transition-colors hover:border-brand hover:text-brand">
                            {link.label} <ArrowRight className="h-4 w-4" />
                        </Link>
                    ))}
                </div>
            )}
        </article>
    );
}

/**
 * Searchable, sectioned guide with a sticky table of contents.
 * `guide` comes from App\Support\Manual\Manual::forDisplay().
 */
export default function GuideViewer({ guide, stickyOffset = 'top-24' }) {
    const [query, setQuery] = useState('');
    const term = query.trim().toLowerCase();

    const sections = useMemo(() => guide.sections
        .map((section) => ({
            ...section,
            articles: term ? section.articles.filter((article) => searchableText(article).includes(term)) : section.articles,
        }))
        .filter((section) => section.articles.length > 0), [guide, term]);

    const matches = sections.reduce((sum, section) => sum + section.articles.length, 0);

    return (
        <div className="grid gap-10 lg:grid-cols-[260px_minmax(0,1fr)]">
            <aside className={cn('lg:sticky lg:self-start', stickyOffset)}>
                <label className="flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 transition focus-within:border-brand focus-within:ring-4 focus-within:ring-brand/10">
                    <Search className="h-4 w-4 shrink-0 text-slate-400" />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search this guide"
                        aria-label="Search this guide"
                        className="h-10 w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400"
                    />
                    {query && (
                        <button type="button" onClick={() => setQuery('')} aria-label="Clear search" className="text-slate-400 hover:text-slate-700">
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </label>

                <nav className="mt-6 hidden max-h-[calc(100vh-12rem)] overflow-y-auto pr-2 lg:block" aria-label="Guide contents">
                    {sections.map((section) => (
                        <div key={section.id} className="mb-5">
                            <a href={`#${section.id}`} className="text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-900">{section.title}</a>
                            <ul className="mt-2 space-y-1.5 border-l border-slate-200">
                                {section.articles.map((article) => (
                                    <li key={article.id}>
                                        <a href={`#${article.id}`} className="-ml-px block border-l border-transparent pl-3 text-sm text-slate-600 hover:border-brand hover:text-slate-900">{article.title}</a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </nav>
            </aside>

            <div className="min-w-0">
                {term && (
                    <p className="mb-6 text-sm text-slate-500">
                        {matches} result{matches === 1 ? '' : 's'} for “{query.trim()}”
                    </p>
                )}
                {sections.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                        <p className="font-semibold text-slate-900">No matching topics</p>
                        <p className="mt-1 text-sm text-slate-500">Try a simpler word, like “rent”, “lease” or “viewing”.</p>
                    </div>
                ) : (
                    <div className="space-y-14">
                        {sections.map((section) => (
                            <section key={section.id} id={section.id} className="scroll-mt-24">
                                <h2 className="text-2xl font-semibold tracking-tight text-slate-900">{section.title}</h2>
                                {section.intro && <p className="mt-1.5 max-w-2xl text-[15px] leading-7 text-slate-500"><RichText text={section.intro} /></p>}
                                <div className="mt-6 space-y-4">
                                    {section.articles.map((article) => <Article key={article.id} article={article} />)}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
