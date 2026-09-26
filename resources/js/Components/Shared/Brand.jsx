import { House } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function Brand({ dark = false, showText = true, size = 36, className }) {
    return (
        <div className={cn('flex items-center gap-2.5', className)}>
            <div
                className={cn('relative grid shrink-0 place-items-center rounded-[10px]', dark ? 'bg-white text-brand' : 'bg-brand text-white')}
                style={{ width: size, height: size }}
            >
                <House style={{ width: size * 0.52, height: size * 0.52 }} strokeWidth={2.3} />
                <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-pitch ring-2 ring-white" />
            </div>
            {showText && (
                <div className="leading-tight">
                    <div className={cn('text-[17px] font-bold tracking-tight', dark ? 'text-white' : 'text-slate-900')}>
                        ZimRent
                    </div>
                    <div className={cn('text-[10px] font-medium uppercase tracking-[0.16em]', dark ? 'text-white/55' : 'text-slate-500')}>
                        Property Platform
                    </div>
                </div>
            )}
        </div>
    );
}
