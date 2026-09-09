import PropTypes from 'prop-types';
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

export default function StatCard({ icon: Icon, label, value, hint, tone = 'emerald', trend }) {
    return (
        <div className="surface group relative overflow-hidden p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_40px_-16px_rgba(16,60,45,0.28)]">
            <span className={cn('absolute inset-x-0 top-0 h-0.5 opacity-0 transition-opacity group-hover:opacity-100 brand-gradient')} />
            <div className="flex items-start justify-between gap-3">
                {Icon && (
                    <span className={cn('grid h-11 w-11 shrink-0 place-items-center rounded-xl ring-1', iconTones[tone] || iconTones.emerald)}>
                        <Icon className="h-5 w-5" strokeWidth={2} />
                    </span>
                )}
                {trend && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[11px] font-bold',
                            trend.direction === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                        )}
                    >
                        {trend.direction === 'up' ? <ArrowUpRight className="h-3 w-3" /> : <ArrowDownRight className="h-3 w-3" />}
                        {trend.value}
                    </span>
                )}
            </div>
            <div className="mt-4 min-w-0">
                <div className="truncate text-sm font-medium text-muted-foreground">{label}</div>
                <div className="mt-0.5 text-[26px] font-extrabold leading-none tracking-tight text-foreground">{value}</div>
                {hint && <div className="mt-1.5 text-xs text-muted-foreground/80">{hint}</div>}
            </div>
        </div>
    );
}

StatCard.propTypes = {
    icon: PropTypes.elementType,
    label: PropTypes.string.isRequired,
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    hint: PropTypes.string,
    tone: PropTypes.oneOf(['emerald', 'teal', 'amber', 'rose', 'sky', 'violet', 'slate', 'indigo']),
    trend: PropTypes.shape({ value: PropTypes.node, direction: PropTypes.oneOf(['up', 'down']) }),
};