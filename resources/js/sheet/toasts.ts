import { ref } from 'vue';

export type Toast = {
    id: number;
    message: string;
    tone: 'error' | 'warning' | 'info';
};

export const toasts = ref<Toast[]>([]);
let next = 1;

export function toast(
    message: string,
    tone: Toast['tone'] = 'info',
    duration = 4000,
) {
    const id = next++;
    toasts.value.push({ id, message, tone });
    window.setTimeout(() => dismissToast(id), duration);
}

export function dismissToast(id: number) {
    toasts.value = toasts.value.filter((entry) => entry.id !== id);
}
