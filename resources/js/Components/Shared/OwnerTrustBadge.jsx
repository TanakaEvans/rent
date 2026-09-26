import { UserCheck, Medal } from 'lucide-react';
import { cn } from '@/lib/utils';
import { badgeTierLabel } from '@/lib/listing';

const TIER_ICON = {
    gold: 'text-amber-500',
    silver: 'text-slate-400',
    bronze: 'text-orange-700',
};

const pill = 'inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-900 shadow-sm ring-1 ring-black/5';

/**
 * The owner/landlord trust pill shown on marketplace surfaces.
 *
 * S2 badge groundwork: a gold/silver/bronze tier is typed from KYC+verification
 * decisions (the S5 flow writes `badge_tier`); when no tier is set it falls
 * back to the plain "Verified Owner" pill driven by the owner `verified` flag.
 */
export default function OwnerTrustBadge({ owner, className }) {
    if (!owner) return null;

    const tier = owner.badge_tier && owner.badge_tier !== 'none' ? owner.badge_tier : null;

    if (tier) {
        return (
            <span className={cn(pill, className)}>
                <Medal className={cn('h-3.5 w-3.5', TIER_ICON[tier] || TIER_ICON.gold)} /> {badgeTierLabel(tier)}
            </span>
        );
    }

    if (!owner.verified) return null;

    return (
        <span className={cn(pill, className)}>
            <UserCheck className="h-3.5 w-3.5 text-brand" /> Verified owner
        </span>
    );
}