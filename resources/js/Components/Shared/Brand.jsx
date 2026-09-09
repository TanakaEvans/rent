import { House } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function Brand({ dark = false, showText = true, size = 40, className }) {
    return (
        <div className={cn('flex items-center gap-2.5', className)}>
            <div
                className="relative grid shrink-0 place-items-center rounded-xl brand-gradient shadow-[0_8px_20px_-8px_rgba(10,140,100,0.55)] ring-1 ring-white/15"
                style={{ width: size, height: size }}
            >
                <House className="text-white" style={{ width: size * 0.5, height: size * 0.5 }} strokeWidth={2.2} />
                <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-[#0d3227]" />
            </div>
            {showText && (
                <div className="leading-tight">
                    <div className={cn('text-lg font-extrabold tracking-tight', dark ? 'text-white' : 'text-foreground')}>
                        Dzimba
                    </div>
                    <div className={cn('text-[10px] font-semibold uppercase tracking-[0.18em]', dark ? 'text-sidebar-accent-foreground/50' : 'text-muted-foreground')}>
                        Property Platform
                    </div>
                </div>
            )}
        </div>
    );
}