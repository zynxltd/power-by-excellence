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
        const loc = currencyOverride ? (LOCALE_BY_CURRENCY[cur] ?? 'en-GB') : locale.value;
        return new Intl.NumberFormat(loc, {
            style: 'currency',
            currency: cur,
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

    return { currency, locale, formatMoney, formatNumber };
}
