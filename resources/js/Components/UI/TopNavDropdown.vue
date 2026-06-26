<script setup>
import { useNavDropdown } from '@/Composables/useNavDropdown';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    title: { type: String, default: null },
    active: { type: Boolean, default: false },
    wide: { type: Boolean, default: false },
});

const nav = useNavDropdown();

const isOpen = computed(() => nav?.isOpen(props.id) ?? false);

const toggle = () => nav?.toggle(props.id);

const buttonRef = ref(null);
const menuStyle = ref({});

const updatePosition = () => {
    const el = buttonRef.value;
    if (! el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const minWidth = props.wide ? 300 : 224;
    let left = rect.left;

    if (left + minWidth > window.innerWidth - 8) {
        left = rect.right - minWidth;
    }

    left = Math.max(8, left);

    menuStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${left}px`,
        minWidth: `${minWidth}px`,
        zIndex: 60,
    };
};

const onScrollOrResize = () => {
    if (isOpen.value) {
        updatePosition();
    }
};

watch(isOpen, async (open) => {
    if (! open) {
        window.removeEventListener('scroll', onScrollOrResize, true);
        window.removeEventListener('resize', onScrollOrResize);

        return;
    }

    await nextTick();
    updatePosition();
    window.addEventListener('scroll', onScrollOrResize, true);
    window.addEventListener('resize', onScrollOrResize);
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
});
</script>

<template>
    <div class="relative shrink-0" data-nav-dropdown>
        <button
            ref="buttonRef"
            type="button"
            :title="title ?? label"
            :class="[
                'flex shrink-0 items-center gap-1 whitespace-nowrap rounded-lg px-2.5 py-2 text-sm font-medium transition',
                isOpen
                    ? 'bg-indigo-600 text-white'
                    : active
                        ? 'bg-indigo-600/20 text-indigo-200 ring-1 ring-indigo-500/40'
                        : 'text-slate-300 hover:bg-slate-800 hover:text-white',
            ]"
            :aria-expanded="isOpen"
            @click.stop="toggle"
        >
            {{ label }}
            <svg
                class="h-3.5 w-3.5 opacity-70 transition"
                :class="isOpen ? 'rotate-180' : ''"
                fill="currentColor"
                viewBox="0 0 20 20"
            >
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <Teleport to="body">
            <div
                v-show="isOpen"
                data-nav-dropdown-menu
                :style="menuStyle"
                :class="[
                    'rounded-xl border border-slate-700 bg-slate-900 py-1 text-slate-100 shadow-xl',
                    wide ? 'py-0' : 'py-1',
                ]"
                @click="wide ? undefined : nav?.close()"
            >
                <slot />
            </div>
        </Teleport>
    </div>
</template>
