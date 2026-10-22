import { onUnmounted, ref } from 'vue';

/**
 * Pointer-based (mouse + touch) drag-to-reorder for items marked with `data-sort-id`.
 * The drag starts on a handle; the list reorders live while dragging and `onDrop` receives
 * the new id order only if it changed. The page auto-scrolls near the viewport edges.
 */
export function useDragSort(options: {
    ids: () => number[];
    onDrop: (ids: number[]) => void;
}) {
    const order = ref<number[] | null>(null);
    const draggingId = ref<number | null>(null);
    let original: number[] = [];
    let pointerX = 0;
    let pointerY = 0;
    let frame = 0;
    let lastSwap: number | null = null;

    // A finger held still near an edge scrolls the page, so re-check what is under it each frame.
    const autoScroll = () => {
        const edge = 80;
        const before = window.scrollY;
        if (pointerY < edge)
            window.scrollBy(0, -Math.ceil((edge - pointerY) / 6));
        else if (pointerY > window.innerHeight - edge)
            window.scrollBy(
                0,
                Math.ceil((pointerY - (window.innerHeight - edge)) / 6),
            );
        if (window.scrollY !== before) hitTest();
        frame = requestAnimationFrame(autoScroll);
    };

    const move = (event: PointerEvent) => {
        pointerX = event.clientX;
        pointerY = event.clientY;
        hitTest();
    };

    const hitTest = () => {
        if (draggingId.value === null || !order.value) return;
        const target = document
            .elementFromPoint(pointerX, pointerY)
            ?.closest<HTMLElement>('[data-sort-id]');
        const targetId = Number(target?.dataset.sortId);
        if (!target || targetId === draggingId.value) {
            lastSwap = null;
            return;
        }
        // Cards animate into their new places; don't swap back with the card we just passed.
        if (targetId === lastSwap || !order.value.includes(targetId)) return;
        lastSwap = targetId;
        const next = order.value.filter((id) => id !== draggingId.value);
        next.splice(
            next.indexOf(targetId) +
                (order.value.indexOf(targetId) >
                order.value.indexOf(draggingId.value)
                    ? 1
                    : 0),
            0,
            draggingId.value,
        );
        order.value = next;
    };

    const finish = (commit: boolean) => {
        cancelAnimationFrame(frame);
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        window.removeEventListener('pointercancel', cancel);
        window.removeEventListener('keydown', escape);
        const result = order.value;
        draggingId.value = null;
        order.value = null;
        if (commit && result && result.join() !== original.join())
            options.onDrop(result);
    };
    const up = () => finish(true);
    const cancel = () => finish(false);
    const escape = (event: KeyboardEvent) =>
        event.key === 'Escape' && finish(false);

    const start = (id: number, event: PointerEvent) => {
        if (event.button !== 0) return;
        event.preventDefault();
        original = options.ids();
        lastSwap = null;
        order.value = [...original];
        draggingId.value = id;
        pointerX = event.clientX;
        pointerY = event.clientY;
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', cancel);
        window.addEventListener('keydown', escape);
        frame = requestAnimationFrame(autoScroll);
    };

    /** Keyboard alternative: move one position earlier (-1) or later (+1) and save. */
    const nudge = (id: number, offset: -1 | 1) => {
        const ids = options.ids();
        const index = ids.indexOf(id);
        const target = index + offset;
        if (index === -1 || target < 0 || target >= ids.length) return;
        [ids[index], ids[target]] = [ids[target], ids[index]];
        options.onDrop(ids);
    };

    onUnmounted(() => finish(false));

    return { order, draggingId, start, nudge };
}
