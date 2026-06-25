<script setup>
defineProps({
    logoUrl: { type: String, default: null },
    brandName: { type: String, default: null },
    size: { type: String, default: 'md' },
    showText: { type: Boolean, default: true },
    variant: { type: String, default: 'light' },
    animated: { type: Boolean, default: true },
});

const sizes = {
    xs: { icon: 'h-6 w-6', text: 'text-sm', img: 'h-6' },
    sm: { icon: 'h-8 w-8', text: 'text-lg', img: 'h-8' },
    md: { icon: 'h-10 w-10', text: 'text-xl', img: 'h-10' },
    lg: { icon: 'h-12 w-12', text: 'text-2xl', img: 'h-12' },
};
</script>

<template>
    <div class="flex items-center gap-2">
        <img
            v-if="logoUrl"
            :src="logoUrl"
            :alt="brandName || 'Platform logo'"
            :class="[sizes[size].img, 'w-auto max-w-[180px] object-contain']"
        />
        <span
            v-else-if="brandName && showText"
            :class="[
                sizes[size].text,
                'max-w-[200px] truncate font-bold tracking-tight',
                variant === 'light' ? 'text-white' : 'text-slate-900',
            ]"
        >
            {{ brandName }}
        </span>
        <template v-else-if="!brandName">
            <div
                :class="[
                    sizes[size].icon,
                    'logo-electric relative flex shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 via-indigo-500 to-cyan-400 p-0.5 shadow-lg shadow-indigo-500/40',
                    animated && 'logo-electric--animated',
                ]"
            >
                <div class="relative flex h-full w-full items-center justify-center overflow-hidden rounded-[10px] bg-slate-950">
                    <div v-if="animated" class="logo-shine pointer-events-none absolute inset-0" />
                    <div v-if="animated" class="logo-spark pointer-events-none absolute inset-0" />
                    <svg viewBox="0 0 24 24" class="relative z-10 h-[55%] w-[55%] logo-bolt" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" fill="url(#bolt-gradient)" />
                        <defs>
                            <linearGradient id="bolt-gradient" x1="4" y1="2" x2="20" y2="22" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#a78bfa" />
                                <stop offset="0.5" stop-color="#6366f1" />
                                <stop offset="1" stop-color="#22d3ee" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
            </div>
            <span
                v-if="showText"
                :class="[
                    sizes[size].text,
                    'font-bold tracking-tight',
                    variant === 'light' ? 'text-white' : 'text-slate-900',
                ]"
            >
                <span class="bg-gradient-to-r from-violet-400 via-indigo-400 to-cyan-400 bg-clip-text text-transparent">Power</span><span :class="variant === 'light' ? 'text-white' : 'text-slate-800'">ByExcellence</span>
            </span>
        </template>
        <span
            v-if="logoUrl && showText && brandName"
            :class="[sizes[size].text, 'max-w-[160px] truncate font-bold tracking-tight', variant === 'light' ? 'text-white' : 'text-slate-900']"
        >
            {{ brandName }}
        </span>
    </div>
</template>

<style scoped>
.logo-electric--animated {
    animation: electric-pulse 3s ease-in-out infinite;
}

.logo-shine {
    background: linear-gradient(
        105deg,
        transparent 35%,
        rgba(255, 255, 255, 0.55) 45%,
        rgba(34, 211, 238, 0.4) 50%,
        transparent 65%
    );
    transform: translateX(-120%);
    animation: shine-sweep 2.8s ease-in-out infinite;
}

.logo-spark {
    background: radial-gradient(circle at 50% 50%, rgba(167, 139, 250, 0.35) 0%, transparent 65%);
    animation: spark-flicker 1.2s ease-in-out infinite alternate;
}

.logo-bolt {
    filter: drop-shadow(0 0 6px rgba(99, 102, 241, 0.8));
    animation: bolt-glow 2s ease-in-out infinite alternate;
}

@keyframes shine-sweep {
    0%, 35% { transform: translateX(-120%); opacity: 0; }
    50% { opacity: 1; }
    65%, 100% { transform: translateX(120%); opacity: 0; }
}

@keyframes electric-pulse {
    0%, 100% { box-shadow: 0 0 12px rgba(99, 102, 241, 0.35), 0 0 24px rgba(34, 211, 238, 0.15); }
    50% { box-shadow: 0 0 20px rgba(167, 139, 250, 0.55), 0 0 40px rgba(34, 211, 238, 0.25); }
}

@keyframes spark-flicker {
    from { opacity: 0.3; }
    to { opacity: 0.85; }
}

@keyframes bolt-glow {
    from { filter: drop-shadow(0 0 4px rgba(99, 102, 241, 0.6)); }
    to { filter: drop-shadow(0 0 10px rgba(34, 211, 238, 0.9)); }
}
</style>
