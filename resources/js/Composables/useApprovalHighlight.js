import { onMounted } from 'vue';

/**
 * Scroll to and highlight a pending approval row when ?approval={id} is present.
 */
export function useApprovalHighlight() {
    onMounted(() => {
        const id = new URLSearchParams(window.location.search).get('approval');

        if (! id) {
            return;
        }

        const el = document.getElementById(`approval-${id}`);

        if (! el) {
            return;
        }

        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('ring-2', 'ring-indigo-500', 'ring-offset-2', 'dark:ring-offset-slate-900');
    });
}
