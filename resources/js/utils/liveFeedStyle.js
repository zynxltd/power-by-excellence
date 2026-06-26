export const feedTypeLabels = {
    lead_event: 'Lead event',
    delivery: 'Delivery',
    access: 'Access',
};

export function feedTypeBadgeClass(type) {
    if (type === 'lead_event') {
        return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300';
    }
    if (type === 'delivery') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    }
    if (type === 'access') {
        return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
    }
    return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
}

export function statusBadgeClass(status) {
    if (!status) return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
    const value = String(status).toLowerCase();
    if (['success', 'sold', 'accepted', 'info', 'passed'].includes(value)) {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    }
    if (['warning', 'skipped', 'pending', 'quarantined'].includes(value)) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
    if (['error', 'failed', 'rejected'].includes(value)) {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300';
    }
    return 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300';
}
