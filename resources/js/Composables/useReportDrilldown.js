import { computed } from 'vue';

/**
 * Build drill-down URLs from Reports page filters so targets open pre-scoped to the same period.
 */
export function useReportDrilldown(props) {
    const periodForLeads = computed(() => {
        const f = props.filters ?? {};
        const base = {};

        if (f.campaign_id) {
            base.campaign_id = f.campaign_id;
        }

        if (f.date_from && f.date_to) {
            return { ...base, from_date: f.date_from, to_date: f.date_to };
        }

        if (f.month) {
            const [year, month] = f.month.split('-').map(Number);
            const start = new Date(year, month - 1, 1);
            const end = new Date(year, month, 0);
            const iso = (d) => d.toISOString().slice(0, 10);

            return { ...base, from_date: iso(start), to_date: iso(end) };
        }

        const to = new Date();
        const from = new Date();
        from.setDate(from.getDate() - ((props.days ?? 28) - 1));

        return {
            ...base,
            from_date: from.toISOString().slice(0, 10),
            to_date: to.toISOString().slice(0, 10),
        };
    });

    const periodForDeliveryLogs = computed(() => {
        const f = props.filters ?? {};
        const base = {};

        if (f.campaign_id) {
            base.campaign_id = f.campaign_id;
        }

        if (f.date_from && f.date_to) {
            return { ...base, date_from: f.date_from, date_to: f.date_to };
        }

        if (f.month) {
            const leads = periodForLeads.value;

            return { ...base, date_from: leads.from_date, date_to: leads.to_date };
        }

        return { ...base, days: props.days ?? 28 };
    });

    const periodForFinance = computed(() => {
        const leads = periodForLeads.value;

        if (leads.from_date && leads.to_date) {
            return { from_date: leads.from_date, to_date: leads.to_date };
        }

        return { days: props.days ?? 28 };
    });

    const leadsDrill = (extra = {}) => route('leads.index', { ...periodForLeads.value, ...extra });

    const deliveryDrill = (extra = {}) => route('logs.delivery', { ...periodForDeliveryLogs.value, ...extra });

    const financeDrill = (extra = {}) => route('finance.index', { ...periodForFinance.value, ...extra });

    const quarantineDrill = (extra = {}) => route('quarantine.index', extra);

    return {
        periodForLeads,
        periodForDeliveryLogs,
        periodForFinance,
        leadsDrill,
        deliveryDrill,
        financeDrill,
        quarantineDrill,
    };
}
