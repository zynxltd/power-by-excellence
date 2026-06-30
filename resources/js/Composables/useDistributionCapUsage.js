import { onMounted, onUnmounted, ref } from 'vue';

const POLL_INTERVAL_MS = 30_000;

export function useDistributionCapUsage(configId, initialUsage = {}) {
    const capUsage = ref({ ...initialUsage });
    let timer = null;

    const refreshCapUsage = async () => {
        if (!configId) {
            return;
        }

        try {
            const response = await fetch(route('distribution.cap-usage', configId), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            capUsage.value = data.cap_usage ?? {};
        } catch {
            // Polling is best-effort for v1.
        }
    };

    onMounted(() => {
        if (!configId) {
            return;
        }

        timer = window.setInterval(refreshCapUsage, POLL_INTERVAL_MS);
    });

    onUnmounted(() => {
        if (timer !== null) {
            window.clearInterval(timer);
        }
    });

    return {
        capUsage,
        refreshCapUsage,
    };
}
