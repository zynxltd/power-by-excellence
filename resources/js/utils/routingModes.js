export const ROUTING_MODE_LABELS = {
    waterfall: 'Waterfall',
    parallel_auction: 'Parallel auction',
    sequential_ping: 'Sequential ping',
    weighted: 'Weighted',
    round_robin: 'Round robin',
    hybrid: 'Hybrid',
};

export const ROUTING_MODE_STYLES = {
    waterfall: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    parallel_auction: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    sequential_ping: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
    weighted: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    round_robin: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    hybrid: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
};

export function routingModeLabel(mode) {
    if (!mode) return '—';
    return ROUTING_MODE_LABELS[mode] ?? mode.replace(/_/g, ' ');
}

export function hasEntryFilters(rules) {
    return (rules?.conditions?.length ?? 0) > 0;
}
