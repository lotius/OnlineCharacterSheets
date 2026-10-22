<script setup lang="ts">
import OverlayCanvas from '@/components/overlay/OverlayCanvas.vue';
import { echo } from '@/echo';
import type { OverlayState } from '@/overlay/types';
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * OBS / browser-source renderer: transparent, no chrome, read-only. Realtime notices trigger a
 * refetch of the filtered overlay state; a slow poll and reconnect hook recover silently.
 */
const props = defineProps<{ token: string; state: OverlayState }>();
const state = ref<OverlayState>(props.state);
const scale = ref(1);
let channel: string | null = null;
let poll: number | undefined;
let queued: number | undefined;
let unbind: (() => void) | null = null;

const fit = () => {
    const config = state.value.overlay.configuration;
    scale.value = Math.min(
        window.innerWidth / config.width,
        window.innerHeight / config.height,
    );
};

const fetchState = async () => {
    try {
        const response = await fetch(`/overlay/${props.token}/state`, {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return; // Revoked or rate-limited: keep showing the last good frame.
        const next = (await response.json()) as OverlayState;
        if (next.overlay.channel !== channel) subscribe(next.overlay.channel);
        state.value = next;
        fit();
    } catch {
        // Offline: the poll will try again.
    }
};
const refresh = () => {
    window.clearTimeout(queued);
    queued = window.setTimeout(fetchState, 120); // Coalesce bursts of notices.
};

function subscribe(name: string) {
    if (channel) echo?.leave(channel);
    channel = name;
    echo?.channel(name).listen('.room.realtime.ping', refresh);
}

onMounted(() => {
    document.documentElement.classList.add('overlay-page');
    subscribe(state.value.overlay.channel);
    if (echo) {
        const connection = echo.connector.pusher.connection;
        const onState = ({ current }: { current: string }) =>
            current === 'connected' && refresh();
        connection.bind('state_change', onState);
        unbind = () => connection.unbind('state_change', onState);
    }
    poll = window.setInterval(fetchState, 20000);
    window.addEventListener('resize', fit);
    fit();
});
onUnmounted(() => {
    if (channel) echo?.leave(channel);
    unbind?.();
    window.clearInterval(poll);
    window.clearTimeout(queued);
    window.removeEventListener('resize', fit);
});
</script>

<template>
    <Head :title="state.overlay.name" />
    <div class="fixed inset-0 overflow-hidden bg-transparent">
        <OverlayCanvas
            :config="state.overlay.configuration"
            :state="state"
            :scale="scale"
        />
    </div>
</template>
