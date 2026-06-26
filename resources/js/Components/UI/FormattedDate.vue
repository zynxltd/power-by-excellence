<script setup>
import { computed } from 'vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const props = defineProps({
    value: { type: [String, Number, Date], default: null },
    format: { type: String, default: 'datetime' },
    class: { type: String, default: '' },
});

const { formatDateTime, formatDate, formatTime, formatRelative } = useDateFormat();

const display = computed(() => {
    if (!props.value) return '—';

    return {
        datetime: formatDateTime(props.value),
        date: formatDate(props.value),
        time: formatTime(props.value),
        relative: formatRelative(props.value),
    }[props.format] ?? formatDateTime(props.value);
});
</script>

<template>
    <time
        v-if="value"
        :datetime="String(value)"
        :title="String(value)"
        :class="['whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400', props.class]"
    >
        {{ display }}
    </time>
    <span v-else :class="['text-slate-400', props.class]">—</span>
</template>
