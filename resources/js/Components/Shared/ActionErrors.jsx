import { usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Banner for validation errors returned by a page action (status changes,
 * approvals, lease steps…). `only` limits it to the given error keys;
 * `exclude` skips keys the page already shows next to a field.
 */
export default function ActionErrors({ only = null, exclude = [], className = '' }) {
    const { errors = {} } = usePage().props;

    const messages = [...new Set(
        Object.entries(errors)
            .filter(([key, message]) => message && (only ? only.includes(key) : !exclude.includes(key)))
            .map(([, message]) => message)
    )];

    if (messages.length === 0) return null;

    return (
        <div role="alert" className={cn('flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800', className)}>
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
            {messages.length === 1 ? (
                <p>{messages[0]}</p>
            ) : (
                <ul className="list-disc space-y-0.5 pl-4">
                    {messages.map((message) => <li key={message}>{message}</li>)}
                </ul>
            )}
        </div>
    );
}
