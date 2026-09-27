import { cn } from '@/lib/utils';

/**
 * Profile photo with a brand-purple monogram fallback. Shown in signed-in
 * areas only — never on the anonymous marketplace.
 *
 * Pass either a `user` (with `avatar_url` + `initials`, as the shared
 * `auth.user` prop provides) or the raw `{ src, name, initials }`.
 */
export default function Avatar({ user = null, src = null, name = null, initials = null, size = 40, className = '' }) {
    const photo = src ?? user?.avatar_url ?? null;
    const label = name ?? user?.name ?? '';

    const derived = label
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();

    const monogram = (initials ?? user?.initials ?? derived ?? '').toUpperCase() || 'U';
    const dimension = { width: size, height: size };

    if (photo) {
        return (
            <img
                src={photo}
                alt={label ? `${label}'s profile photo` : 'Profile photo'}
                style={dimension}
                className={cn('shrink-0 rounded-full object-cover', className)}
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            style={{ ...dimension, fontSize: Math.max(11, Math.round(size * 0.4)) }}
            className={cn('grid shrink-0 place-items-center rounded-full brand-gradient font-extrabold leading-none text-white', className)}
        >
            {monogram}
        </span>
    );
}
