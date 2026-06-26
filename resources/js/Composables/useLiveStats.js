import { inject, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const LIVE_STATS_KEY = Symbol('liveStats');

function resolveCampaignId() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('campaign_id');
    return id ? Number(id) : null;
}

export function provideLiveStats() {
    const page = usePage();
    const stats = ref(null);
    const loading = ref(false);
    const error = ref(null);
    const campaignId = ref(resolveCampaignId());

    const intervalSeconds = page.props.platform?.liveStatsInterval ?? 15;
    let timer = null;

    const buildUrl = () => {
        const url = new URL(route('live-stats'), window.location.origin);
        if (campaignId.value) {
            url.searchParams.set('campaign_id', String(campaignId.value));
        }
        return url.toString();
    };

    const refresh = async () => {
        if (! page.props.auth?.showLiveStats) {
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(buildUrl(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Live stats failed (${response.status})`);
            }

            stats.value = await response.json();
        } catch (e) {
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    };

    watch(
        () => page.url,
        () => {
            const next = resolveCampaignId();
            if (next !== campaignId.value) {
                campaignId.value = next;
                refresh();
            }
        },
    );

    onMounted(() => {
        if (! page.props.auth?.showLiveStats) {
            return;
        }

        refresh();
        timer = setInterval(refresh, intervalSeconds * 1000);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
        }
    });

    const api = { stats, loading, error, refresh, intervalSeconds, campaignId };

    return { ...api, provideKey: LIVE_STATS_KEY, provideValue: api };
}

export function useLiveStats() {
    return inject(LIVE_STATS_KEY, {
        stats: ref(null),
        loading: ref(false),
        error: ref(null),
        refresh: () => {},
        intervalSeconds: 15,
        campaignId: ref(null),
    });
}
