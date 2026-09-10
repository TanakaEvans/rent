import PropTypes from 'prop-types';
import { cn } from '@/lib/utils';

const tones = {
    slate: { badge: 'bg-slate-100 text-slate-600 border-slate-200', dot: 'bg-slate-400' },
    sky: { badge: 'bg-sky-50 text-sky-700 border-sky-200', dot: 'bg-sky-500' },
    emerald: { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    amber: { badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
    rose: { badge: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' },
    violet: { badge: 'bg-violet-50 text-violet-700 border-violet-200', dot: 'bg-violet-500' },
    orange: { badge: 'bg-orange-50 text-orange-700 border-orange-200', dot: 'bg-orange-500' },
};

const statusTone = {
    draft: 'slate',
    under_review: 'sky',
    approved: 'emerald',
    issued: 'emerald',
    cancelled: 'slate',
    revoked: 'rose',
    superseded: 'orange',
    active: 'emerald',
    paused: 'amber',
    archived: 'slate',
    inactive: 'slate',
    available: 'emerald',
    reserved: 'amber',
    occupied: 'sky',
    unavailable: 'slate',
    pending: 'amber',
    shortlisted: 'violet',
    rejected: 'rose',
    new: 'sky',
    read: 'amber',
    replied: 'emerald',
    closed: 'slate',
    taken: 'sky',
    expired: 'slate',
    requested: 'amber',
    accepted: 'emerald',
    rescheduled: 'violet',
    declined: 'rose',
    completed: 'sky',
    'no-show': 'orange',
    sent: 'sky',
    signed: 'violet',
    renewed: 'sky',
    terminated: 'slate',
    grace: 'amber',
    suspended: 'rose',
    paid: 'emerald',
    due: 'amber',
    overdue: 'rose',
    refunded: 'sky',
    settled: 'emerald',
    low: 'slate',
    medium: 'amber',
    high: 'rose',
    critical: 'orange',
    open: 'amber',
    escalated: 'orange',
    dismissed: 'slate',
    resolved: 'emerald',
};

export default function StatusBadge({ status, className, showDot = true }) {
    const display = status ? status.replace(/_/g, ' ') : '—';
    const tone = tones[statusTone[status] || 'slate'];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-[11px] font-semibold capitalize shadow-sm',
                tone.badge,
                className
            )}
        >
            {showDot && <span className={cn('h-1.5 w-1.5 rounded-full', tone.dot)} />}
            {display}
        </span>
    );
}

StatusBadge.propTypes = {
    status: PropTypes.string,
    className: PropTypes.string,
    showDot: PropTypes.bool,
};