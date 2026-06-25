import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useDateFormat() {
    const page = usePage();
    const timezone = computed(() => page.props.auth?.account?.timezone ?? 'UTC');

    const parse = (value) => {
        if (!value) return null;
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const formatDateTime = (value, options = {}) => {
        const date = parse(value);
        if (!date) return '—';

        return new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone.value,
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            ...options,
        }).format(date);
    };

    const formatDate = (value, options = {}) => {
        const date = parse(value);
        if (!date) return '—';

        return new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone.value,
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            ...options,
        }).format(date);
    };

    const formatTime = (value) => {
        const date = parse(value);
        if (!date) return '—';

        return new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone.value,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(date);
    };

    const formatRelative = (value) => {
        const date = parse(value);
        if (!date) return '—';

        const now = Date.now();
        const diffSec = Math.round((date.getTime() - now) / 1000);
        const abs = Math.abs(diffSec);
        const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

        if (abs < 60) return rtf.format(diffSec, 'second');
        if (abs < 3600) return rtf.format(Math.round(diffSec / 60), 'minute');
        if (abs < 86400) return rtf.format(Math.round(diffSec / 3600), 'hour');
        if (abs < 604800) return rtf.format(Math.round(diffSec / 86400), 'day');

        return formatDateTime(value);
    };

    return { timezone, formatDateTime, formatDate, formatTime, formatRelative };
}
