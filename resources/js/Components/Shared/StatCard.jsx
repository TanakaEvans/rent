import PropTypes from 'prop-types';
import { Link } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight } from 'lucide-react';
import { cn } from '@/lib/utils';

const iconTones = {
    emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-600/10',
    teal: 'bg-teal-100 text-teal-700 ring-teal-600/10',
    amber: 'bg-amber-100 text-amber-700 ring-amber-600/10',
    rose: 'bg-rose-100 text-rose-700 ring-rose-600/10',
    sky: 'bg-sky-100 text-sky-700 ring-sky-600/10',
    violet: 'bg-violet-100 text-violet-700 ring-violet-600/10',
    slate: 'bg-slate-100 text-slate-700 ring-slate-600/10',
    indigo: 'bg-indigo-100 text-indigo-700 ring-indigo-600/10',
};

const safeRoute = (name, params = {}) => {
    try { return route(name, params); } catch { return null; }
};

export default function StatCard({ icon: Icon, label, value, hint, tone = 'emerald', trend, href, routeName, routeParams }) {
    const linkHref = href || (routeName ? safeRoute(routeName, routeParams) : null);
    const Tag = linkHref ? Link : 'div';

    return (
        <Tag
            {...(linkHref ? { href: linkHref } : {})}
            className={cn(
                'surface group relative flex items-center gap-3 overflow-hidden p-3.5 transition-all duration-300',
                linkHref && 'hover:-translate-y-0.5 hover:shadow-[0_16px_40px_-16px_rgba(16,60,45,0.28)] cursor-pointer'
            )}
        >
            <span className={cn('absolute inset-y-0 left-0 w-0.5 opacity-0 transition-opacity group-hover:opacity-100 brand-gradient')} />
            {Icon && (
                <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-lg ring-1', iconTones[tone] || iconTones.emerald)}>
                    <Icon className="h-[18px] w-[18px]" strokeWidth={2} />
                </span>
            )}
            <div className="min-w-0 flex-1">
                <div className="truncate text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</div>
                <div className="mt-0.5 flex items-center gap-1.5">
                    <span className="truncate text-xl font-extrabold leading-none tracking-tight text-foreground">{value}</span>
                    {trend && (
                        <span
                            className={cn(
                                'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                trend.direction === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                            )}
                        >
                            {trend.direction === 'up' ? <ArrowUpRight className="h-2.5 w-2.5" /> : <ArrowDownRight className="h-2.5 w-2.5" />}
                            {trend.value}
                        </span>
                    )}
                </div>
                {hint && <div className="mt-0.5 truncate text-[11px] text-muted-foreground/80">{hint}</div>}
            </div>
        </Tag>
    );
}

StatCard.propTypes = {
    icon: PropTypes.elementType,
    label: PropTypes.string.isRequired,
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    hint: PropTypes.string,
    tone: PropTypes.oneOf(['emerald', 'teal', 'amber', 'rose', 'sky', 'violet', 'slate', 'indigo']),
    trend: PropTypes.shape({ value: PropTypes.node, direction: PropTypes.oneOf(['up', 'down']) }),
    href: PropTypes.string,
    routeName: PropTypes.string,
    routeParams: PropTypes.object,
};