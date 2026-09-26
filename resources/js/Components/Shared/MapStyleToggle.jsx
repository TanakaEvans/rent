import PropTypes from 'prop-types';
import { cn } from '@/lib/utils';

const OPTIONS = [['map', 'Map'], ['satellite', 'Satellite']];

/** Segmented Map / Satellite switch that floats over a Leaflet map. */
export default function MapStyleToggle({ value, onChange, className }) {
    return (
        <div className={cn('absolute left-3 top-3 z-[1000] flex rounded-xl bg-white p-1 shadow-[0_2px_10px_rgba(0,0,0,0.14)]', className)} role="group" aria-label="Map style">
            {OPTIONS.map(([key, label]) => (
                <button
                    key={key}
                    type="button"
                    aria-pressed={value === key}
                    onClick={() => onChange(key)}
                    className={cn(
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                        value === key ? 'bg-brand text-white' : 'text-slate-600 hover:text-slate-900'
                    )}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}

MapStyleToggle.propTypes = {
    value: PropTypes.oneOf(['map', 'satellite']).isRequired,
    onChange: PropTypes.func.isRequired,
    className: PropTypes.string,
};
