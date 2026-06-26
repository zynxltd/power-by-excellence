<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white dark:bg-slate-800',
    },
    teleport: {
        type: Boolean,
        default: false,
    },
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

const widthClass = computed(() => ({
    48: 'w-48',
    56: 'w-56',
    72: 'w-72',
}[props.width.toString()] ?? 'w-48'));

const widthPx = computed(() => ({
    48: 192,
    56: 224,
    72: 288,
}[props.width.toString()] ?? 192));

const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    }

    if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    }

    return 'origin-top';
});

const open = ref(false);
const triggerRef = ref(null);
const menuStyle = ref({});

const updatePosition = () => {
    const el = triggerRef.value;
    if (! el || ! props.teleport) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const menuWidth = widthPx.value;
    let left = props.align === 'right' ? rect.right - menuWidth : rect.left;

    left = Math.max(8, Math.min(left, window.innerWidth - menuWidth - 8));

    menuStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 8}px`,
        left: `${left}px`,
        width: `${menuWidth}px`,
        zIndex: 60,
    };
};

const onScrollOrResize = () => {
    if (open.value && props.teleport) {
        updatePosition();
    }
};

watch(open, async (isOpen) => {
    if (! isOpen) {
        window.removeEventListener('scroll', onScrollOrResize, true);
        window.removeEventListener('resize', onScrollOrResize);

        return;
    }

    if (props.teleport) {
        await nextTick();
        updatePosition();
        window.addEventListener('scroll', onScrollOrResize, true);
        window.addEventListener('resize', onScrollOrResize);
    }
});

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
});

const toggle = () => {
    open.value = ! open.value;
};

const close = () => {
    open.value = false;
};
</script>

<template>
    <div class="relative">
        <div ref="triggerRef" @click="toggle">
            <slot name="trigger" />
        </div>

        <div
            v-show="open"
            class="fixed inset-0 z-40"
            @click="close"
        />

        <Teleport to="body" :disabled="!teleport">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-show="open"
                    :class="teleport ? '' : ['absolute z-50 mt-2 shadow-lg', widthClass, alignmentClasses]"
                    :style="teleport ? menuStyle : undefined"
                    @click="close"
                >
                    <div
                        :class="[
                            'overflow-hidden rounded-md shadow-lg ring-1 ring-slate-700/50',
                            contentClasses,
                        ]"
                    >
                        <slot name="content" />
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
