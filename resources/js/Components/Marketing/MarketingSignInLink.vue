<script setup>
import { Link } from '@inertiajs/vue3';
import { useMarketingSignIn } from '@/Composables/useMarketingSignIn';

defineProps({
    class: { type: [String, Object, Array], default: '' },
});

const { signInUrl, isAuthenticated, isExternalSignIn } = useMarketingSignIn();
</script>

<template>
    <a v-if="isExternalSignIn" :href="signInUrl" :class="$props.class">
        <slot>{{ isAuthenticated ? 'Go to Platform' : 'Sign In' }}</slot>
    </a>
    <Link v-else :href="signInUrl" :class="$props.class">
        <slot>{{ isAuthenticated ? 'Go to Platform' : 'Sign In' }}</slot>
    </Link>
</template>
