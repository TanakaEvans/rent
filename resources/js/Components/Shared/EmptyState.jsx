import PropTypes from 'prop-types';
import { cn } from '@/lib/utils';

export default function EmptyState({ icon: Icon, title, description, action, className }) {
    return (
        <div className={cn('surface flex flex-col items-center px-6 py-14 text-center', className)}>
            {Icon && (
                <div className="mb-5 grid h-16 w-16 place-items-center rounded-2xl brand-gradient-soft ring-1 ring-border">
                    <Icon className="h-7 w-7 text-primary" strokeWidth={1.8} />
                </div>
            )}
            <h3 className="text-lg font-bold tracking-tight text-foreground">{title}</h3>
            {description && <p className="mt-1.5 max-w-sm text-sm leading-relaxed text-muted-foreground">{description}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}

EmptyState.propTypes = {
    icon: PropTypes.elementType,
    title: PropTypes.string.isRequired,
    description: PropTypes.string,
    action: PropTypes.node,
    className: PropTypes.string,
};