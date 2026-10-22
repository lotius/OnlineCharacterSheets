import type { Directive } from 'vue';

/**
 * `v-fit` keeps text on one line: if it overflows, the font shrinks in small steps down to a fraction of
 * its normal size (default 0.5 = half; pass e.g. `v-fit="0.7"` to change it). Use with `truncate` so
 * anything still too long at that size ends in "…". Refits when the element resizes or its text changes.
 */
type FitElement = HTMLElement & {
    _fitObserver?: ResizeObserver;
    _fitMin?: number;
    _fitWidth?: number;
};

function fit(el: FitElement) {
    el.style.fontSize = '';
    if (el.scrollWidth <= el.clientWidth) return;
    let size = parseFloat(getComputedStyle(el).fontSize);
    const min = size * (el._fitMin ?? 0.5);
    while (el.scrollWidth > el.clientWidth && size > min) {
        size = Math.max(min, size - 0.5);
        el.style.fontSize = `${size}px`;
    }
}

// Web fonts can finish loading after the first fit and make text wider without resizing the element.
const fitted = new Set<FitElement>();
if (typeof document !== 'undefined' && document.fonts) {
    const refitAll = () => fitted.forEach((el) => fit(el));
    document.fonts.addEventListener('loadingdone', refitAll);
    void document.fonts.ready.then(refitAll);
}

export const vFit: Directive<FitElement, number | undefined> = {
    mounted(el, binding) {
        el._fitMin = binding.value;
        // Only refit on width changes; shrinking the font changes height and must not re-trigger.
        el._fitObserver = new ResizeObserver(([entry]) => {
            if (entry.contentRect.width === el._fitWidth) return;
            el._fitWidth = entry.contentRect.width;
            fit(el);
        });
        el._fitObserver.observe(el);
        fitted.add(el);
        fit(el);
    },
    updated(el, binding) {
        el._fitMin = binding.value;
        fit(el);
    },
    unmounted(el) {
        fitted.delete(el);
        el._fitObserver?.disconnect();
    },
};
