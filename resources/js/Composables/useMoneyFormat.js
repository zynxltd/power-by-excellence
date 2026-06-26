import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const LOCALE_BY_CURRENCY = {
    GBP: 'en-GB',
    USD: 'en-US',
    CAD: 'en-CA',
    AUD: 'en-AU',
    NZD: 'en-NZ',
    EUR: 'de-DE',
    ZAR: 'en-ZA',
    INR: 'en-IN',
    AED: 'ar-AE',
};

/** Currency layout locale — symbol always before the amount (e.g. €25.03 not 25,03 €). */
const MONEY_FORMAT_LOCALE = 'en-GB';

export function useMoneyFormat(overrideCurrency = null) {
    const page = usePage();

    const currency = computed(() => {
        if (overrideCurrency) return String(overrideCurrency).toUpperCase();
        return (
            page.props.platform?.currency
            ?? page.props.auth?.account?.default_currency
            ?? 'GBP'
        ).toUpperCase();
    });

    const locale = computed(() => {
        return page.props.platform?.locale ?? LOCALE_BY_CURRENCY[currency.value] ?? 'en-GB';
    });

    const formatMoney = (amount, options = {}) => {
        const { decimals = 2, compact = false, currency: currencyOverride = null } = options;
        const cur = (currencyOverride ?? currency.value).toUpperCase();

        return new Intl.NumberFormat(MONEY_FORMAT_LOCALE, {
            style: 'currency',
            currency: cur,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
            notation: compact ? 'compact' : 'standard',
        }).format(Number(amount ?? 0));
    };

    const formatNumber = (amount, options = {}) => {
        const { decimals = 0 } = options;
        return new Intl.NumberFormat(locale.value, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(Number(amount ?? 0));
    };

    const formatMoneyMulti = (rows, options = {}) => formatMoneyMultiRows(rows, formatMoney, options);

    return { currency, locale, formatMoney, formatNumber, formatMoneyMulti };
}

export function formatMoneyMultiRows(rows, formatMoney, options = {}) {
    if (!rows?.length) {
        return '—';
    }

    const { decimals = 0, field = 'revenue' } = options;

    if (rows.length === 1) {
        return formatMoney(rows[0][field], { decimals, currency: rows[0].currency });
    }

    return rows
        .filter((row) => Number(row[field] ?? 0) !== 0)
        .map((row) => formatMoney(row[field], { decimals, currency: row.currency }))
        .join(' · ') || '—';
}
