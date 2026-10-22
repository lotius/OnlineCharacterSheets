import { reactive, watch } from 'vue';

/** Per-device view preferences. Layout content is configured by the DM; this only changes how this player sees it. */
type Prefs = {
    density: 'comfortable' | 'compact';
    collapsed: Record<string, boolean>;
    hidden: Record<string, boolean>;
};

const KEY = 'ocs.sheet-prefs.v1';

function read(): Prefs {
    try {
        const stored = JSON.parse(
            window.localStorage.getItem(KEY) ?? 'null',
        ) as Partial<Prefs> | null;
        return {
            density: stored?.density === 'compact' ? 'compact' : 'comfortable',
            collapsed: stored?.collapsed ?? {},
            hidden: stored?.hidden ?? {},
        };
    } catch {
        return { density: 'comfortable', collapsed: {}, hidden: {} };
    }
}

export const prefs = reactive<Prefs>(
    typeof window === 'undefined'
        ? { density: 'comfortable', collapsed: {}, hidden: {} }
        : read(),
);

watch(
    prefs,
    (value) => {
        try {
            window.localStorage.setItem(KEY, JSON.stringify(value));
        } catch {
            // Private mode or blocked storage: preferences just won't persist.
        }
    },
    { deep: true },
);

export function isCollapsed(key: string, fallback: boolean) {
    return prefs.collapsed[key] ?? fallback;
}

export function toggleCollapsed(key: string, fallback: boolean) {
    prefs.collapsed[key] = !isCollapsed(key, fallback);
}

export function resetPrefs() {
    prefs.density = 'comfortable';
    prefs.collapsed = {};
    prefs.hidden = {};
}
