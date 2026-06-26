export const eventLabels = {
    'pipeline.started': 'Processing started',
    'pipeline.completed': 'Processing completed',
    'lead.ingested': 'Lead received via API',
    'lead.validated': 'Validation passed',
    'lead.quarantined': 'Held in quarantine',
    'lead.quarantine_expired': 'Quarantine expired',
    'lead.duplicate': 'Marked as duplicate',
    'lead.rejected': 'Rejected',
    'lead.sold': 'Sold to buyer',
    'lead.unsold': 'No buyer accepted',
    'lead.test_mode': 'Test mode',
    'validation.passed': 'Validation passed',
    'validation.failed': 'Validation failed',
    'dedupe.rejected': 'Duplicate rejected',
    'distribution.tier_filtered': 'Tier entry filter',
    'distribution.skipped_group': 'Hybrid rule skipped',
    'delivery.success': 'Delivery succeeded',
    'delivery.filter_rejected': 'Delivery filter',
    'auction.won': 'Auction won',
    'billing.charge_failed': 'Billing charge failed',
    'auto_responder.sent': 'Auto responder sent',
    'automation.step_sent': 'Automation step sent',
    'postback.sent': 'Postback sent',
    sold: 'Sold',
    processed: 'Processed',
};

export function formatEventType(type) {
    return eventLabels[type] ?? type?.replace(/[._]/g, ' ') ?? type;
}

export function levelBadgeClass(level) {
    if (level === 'error') {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300';
    }
    if (level === 'warning') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
    return 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300';
}

export function eventTypeBadgeClass(eventType, level) {
    if (level === 'error' || /rejected|failed|unsold|duplicate/i.test(eventType ?? '')) {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300';
    }
    if (level === 'warning' || /quarantine|skipped/i.test(eventType ?? '')) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
    if (/sold|won|success|passed|completed/i.test(eventType ?? '')) {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    }
    if (/pipeline|ingested|started/i.test(eventType ?? '')) {
        return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300';
    }
    return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
}
