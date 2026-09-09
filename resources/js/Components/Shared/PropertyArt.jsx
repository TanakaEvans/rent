import { cn } from '@/lib/utils';

const palettes = [
    'from-emerald-600 via-teal-600 to-emerald-900',
    'from-teal-600 via-emerald-700 to-slate-900',
    'from-amber-500 via-emerald-600 to-teal-900',
    'from-emerald-500 via-emerald-700 to-slate-800',
    'from-teal-500 via-emerald-600 to-emerald-950',
    'from-cyan-600 via-teal-700 to-slate-900',
];

const hashSeed = (value) => {
    const s = String(value ?? 'dzimba').split('').reduce((acc, ch) => acc + ch.charCodeAt(0), 0);
    return s;
};

function Doors({ count }) {
    return (
        <g>
            {Array.from({ length: count }).map((_, i) => (
                <rect key={i} x={(30 + i * 34) - count * 6} y={72} width={20} height={28} rx={3} fill="oklch(0.14 0.03 175 / 0.9)" stroke="rgba(255,255,255,0.5)" strokeWidth={1.4} />
            ))}
        </g>
    );
}

function Windows({ cols, rows }) {
    const boxes = [];
    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            boxes.push(
                <rect
                    key={`${r}-${c}`}
                    x={44 + c * 36 + (cols - 2) * -2}
                    y={38 + r * 26}
                    width={22}
                    height={17}
                    rx={2.5}
                    fill={r === 0 && c % 2 === 0 ? 'oklch(0.88 0.09 180 / 0.9)' : 'oklch(0.7 0.05 180 / 0.55)'}
                    stroke="rgba(255,255,255,0.65)"
                    strokeWidth={1.3}
                />
            );
        }
    }
    return <g>{boxes}</g>;
}

export default function PropertyArt({ property, className, icon }) {
    const seed = hashSeed(property?.id ?? property?.title);
    const palette = palettes[seed % palettes.length];
    const cols = 2 + (seed % 2);
    const rows = 2 + ((seed >> 2) % 2);
    const doors = 1 + ((seed >> 3) % 2);

    return (
        <div className={cn('relative flex h-full w-full items-center justify-center overflow-hidden bg-gradient-to-br', palette, className)}>
            <span className="absolute right-6 top-5 h-14 w-14 rounded-full bg-amber-200/25 blur-2xl animate-glow" />
            <span className="absolute -left-10 -top-12 h-36 w-36 rounded-full bg-white/10 blur-2xl" />
            <svg viewBox="0 0 140 100" className="absolute inset-x-0 bottom-0 h-[92%] w-full max-w-[92%] drop-shadow-xl" aria-hidden>
                <g strokeLinecap="round" strokeLinejoin="round">
                    <path d="M14 62 L70 20 L126 62" fill="none" stroke="rgba(255,255,255,0.5)" strokeWidth={1.4} />
                    <rect x={16} y={60} width={108} height={38} rx={4} fill="oklch(0.16 0.03 175 / 0.82)" stroke="rgba(255,255,255,0.5)" strokeWidth={1.4} />
                    <Doors count={doors} />
                    <Windows cols={cols} rows={rows} />
                    <path d="M34 63 L70 31 L106 63" fill="none" stroke="rgba(255,255,255,0.85)" strokeWidth={2.2} />
                </g>
            </svg>
            {icon && <span className="absolute bottom-3 left-3 z-10 text-[10px] font-bold uppercase tracking-wider text-white/85 drop-shadow">{icon}</span>}
        </div>
    );
}