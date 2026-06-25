import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;
const recentMessages = new Map();

export function pushToast(message, type = 'success') {
    if (!message) {
        return;
    }

    const key = `${type}:${message}`;
    const now = Date.now();
    const last = recentMessages.get(key);

    if (last && now - last < 4000) {
        return;
    }

    recentMessages.set(key, now);

    const id = ++nextId;
    toasts.value.push({ id, message, type });
    setTimeout(() => removeToast(id), 6000);
}

export function removeToast(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

export function useToast() {
    return { toasts, push: pushToast, remove: removeToast };
}
