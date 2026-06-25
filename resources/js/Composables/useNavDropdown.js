import { inject, onMounted, onUnmounted, provide, ref } from 'vue';

const KEY = Symbol('navDropdown');

export function provideNavDropdown() {
    const openId = ref(null);

    const onDocumentClick = (event) => {
        const target = event.target;
        if (target instanceof Element && target.closest('[data-nav-dropdown]')) {
            return;
        }
        openId.value = null;
    };

    onMounted(() => document.addEventListener('click', onDocumentClick, true));
    onUnmounted(() => document.removeEventListener('click', onDocumentClick, true));

    const api = {
        openId,
        toggle(id) {
            openId.value = openId.value === id ? null : id;
        },
        close() {
            openId.value = null;
        },
        isOpen(id) {
            return openId.value === id;
        },
    };

    provide(KEY, api);

    return api;
}

export function useNavDropdown() {
    return inject(KEY, null);
}
