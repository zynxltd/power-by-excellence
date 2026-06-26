<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    showControls: { type: Boolean, default: true },
    scrollStep: { type: Number, default: 300 },
});

const scroller = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
const isDragging = ref(false);

let dragStartX = 0;
let scrollStartLeft = 0;
let movedWhileDragging = false;

const updateScrollState = () => {
    const el = scroller.value;
    if (!el) return;

    const max = el.scrollWidth - el.clientWidth;
    canScrollLeft.value = el.scrollLeft > 4;
    canScrollRight.value = el.scrollLeft < max - 4;
};

const scrollBy = (delta) => {
    scroller.value?.scrollBy({ left: delta, behavior: 'smooth' });
};

const onPointerDown = (event) => {
    if (event.button !== 0 || !scroller.value) return;
    if (event.target.closest('a, button, input, select, textarea, label')) return;

    isDragging.value = true;
    movedWhileDragging = false;
    dragStartX = event.clientX;
    scrollStartLeft = scroller.value.scrollLeft;
    scroller.value.setPointerCapture?.(event.pointerId);
};

const onPointerMove = (event) => {
    if (!isDragging.value || !scroller.value) return;

    const delta = event.clientX - dragStartX;
    if (Math.abs(delta) > 3) {
        movedWhileDragging = true;
    }
    scroller.value.scrollLeft = scrollStartLeft - delta;
    updateScrollState();
};

const endDrag = (event) => {
    if (!isDragging.value) return;
    isDragging.value = false;
    scroller.value?.releasePointerCapture?.(event.pointerId);
};

const onClickCapture = (event) => {
    if (movedWhileDragging) {
        event.preventDefault();
        event.stopPropagation();
        movedWhileDragging = false;
    }
};

let resizeObserver = null;

onMounted(() => {
    updateScrollState();
    scroller.value?.addEventListener('scroll', updateScrollState, { passive: true });
    window.addEventListener('resize', updateScrollState);

    if (scroller.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(updateScrollState);
        resizeObserver.observe(scroller.value);
    }
});

onUnmounted(() => {
    scroller.value?.removeEventListener('scroll', updateScrollState);
    window.removeEventListener('resize', updateScrollState);
    resizeObserver?.disconnect();
});
</script>

<template>
    <div class="relative">
        <button
            v-if="showControls && canScrollLeft"
            type="button"
            class="absolute left-0 top-1/2 z-10 -translate-y-1/2 rounded-full border border-slate-200 bg-white/95 p-2 shadow-md transition hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-900/95 dark:hover:bg-indigo-950/50"
            aria-label="Scroll left"
            @click="scrollBy(-scrollStep)"
        >
            <svg class="h-5 w-5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>
        <button
            v-if="showControls && canScrollRight"
            type="button"
            class="absolute right-0 top-1/2 z-10 -translate-y-1/2 rounded-full border border-slate-200 bg-white/95 p-2 shadow-md transition hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-900/95 dark:hover:bg-indigo-950/50"
            aria-label="Scroll right"
            @click="scrollBy(scrollStep)"
        >
            <svg class="h-5 w-5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <div
            v-if="canScrollLeft"
            class="pointer-events-none absolute inset-y-0 left-0 z-[1] w-8 bg-gradient-to-r from-white to-transparent dark:from-slate-900"
        />
        <div
            v-if="canScrollRight"
            class="pointer-events-none absolute inset-y-0 right-0 z-[1] w-8 bg-gradient-to-l from-white to-transparent dark:from-slate-900"
        />

        <div
            ref="scroller"
            class="swipe-scroll -mx-1 overflow-x-auto overscroll-x-contain px-1 pb-2 touch-pan-x"
            :class="isDragging ? 'cursor-grabbing select-none' : 'cursor-grab'"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="endDrag"
            @pointercancel="endDrag"
            @pointerleave="endDrag"
            @click.capture="onClickCapture"
        >
            <div class="flex min-w-min snap-x snap-mandatory gap-4">
                <slot />
            </div>
        </div>

        <p v-if="showControls && (canScrollLeft || canScrollRight)" class="mt-1 text-center text-[10px] text-slate-400">
            Drag, swipe, or use the arrows to see more
        </p>
    </div>
</template>

<style scoped>
.swipe-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
    -webkit-overflow-scrolling: touch;
}

.swipe-scroll::-webkit-scrollbar {
    height: 6px;
}

.swipe-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgb(203 213 225);
}

:global(.dark) .swipe-scroll::-webkit-scrollbar-thumb {
    background: rgb(71 85 105);
}
</style>
