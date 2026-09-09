import { useForm } from '@inertiajs/react';

/**
 * Inline signature capture used by both Lease parties.
 * Empty payload defaults to the user's name server-side.
 */
export default function LeaseSignPad({ label = 'Sign', routeName, leaseId, userName = '' }) {
    const { data, setData, post, processing, errors } = useForm({ signature: userName });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route(routeName, leaseId), { preserveScroll: true });
            }}
            className="flex flex-col gap-2 sm:flex-row sm:items-center"
        >
            <input
                type="text"
                value={data.signature}
                onChange={(e) => setData('signature', e.target.value)}
                placeholder="Signature (defaults to your name)"
                maxLength={255}
                className="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:w-64"
            />
            <button
                type="submit"
                disabled={processing}
                className="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {processing ? 'Signing…' : label}
            </button>
            {errors.signature && <p className="text-xs font-medium text-destructive">{errors.signature}</p>}
        </form>
    );
}