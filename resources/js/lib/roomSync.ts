import { echo } from '@/echo';
import { RevisionGate } from '@/lib/revisionGate';
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

type Room = { id: number; current_revision: number; is_spectator: boolean };
type Notice = { revision: number; visibility: string; room_name: string };
type Options = { only: string[]; characterId?: number; onViewerCount?: (count: number) => void };

// Notifications invalidate local state; only an HTTP response advances the applied revision.
export function useRoomSync(room: () => Room, options: Options) {
    const status = ref<'connecting' | 'connected' | 'error'>('connecting');
    const syncError = ref(false);
    let disposed = false;
    const gate = new RevisionGate();
    gate.target = room().current_revision;
    let poll: number | undefined;
    let channelName: string | null = null;
    let abort: AbortController | null = null;
    let unbind: (() => void) | null = null;

    const leave = () => {
        if (channelName) echo?.leave(channelName);
        channelName = null;
    };
    const accessLost = () => {
        leave();
        router.visit('/rooms');
    };
    const reload = () => {
        if (disposed || !navigator.onLine) return;
        if (!gate.begin()) return;
        let succeeded = false;
        router.reload({
            only: options.only,
            onSuccess: () => {
                succeeded = true;
                syncError.value = false;
            },
            onFinish: () => {
                if (!succeeded) syncError.value = true;
                if (!disposed && gate.finish(room().current_revision, succeeded)) {
                    // Coalesce bursts while preserving a notification that arrived during the fetch.
                    window.setTimeout(reload, 50);
                }
            },
        });
    };
    const check = async () => {
        if (disposed || abort || !navigator.onLine || document.hidden) return;
        abort = new AbortController();
        try {
            const query = options.characterId ? `?character_id=${options.characterId}` : '';
            const response = await fetch(`/games/${room().id}/revision${query}`, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: abort.signal,
            });
            if (disposed) return;
            if ([401, 403, 404].includes(response.status)) {
                accessLost();
                return;
            }
            if (!response.ok) throw new Error('Revision check failed');
            const state = await response.json() as { revision: number; is_spectator: boolean };
            if (!Number.isSafeInteger(state.revision)) throw new Error('Invalid revision');
            gate.target = state.revision;
            if (state.revision !== room().current_revision || state.is_spectator !== room().is_spectator || syncError.value) reload();
        } catch (error) {
            if (!disposed && !(error instanceof DOMException && error.name === 'AbortError')) syncError.value = true;
        } finally {
            abort = null;
        }
    };
    const subscribe = () => {
        leave();
        if (!echo) {
            status.value = 'error';
            return;
        }
        const channel = room().is_spectator ? echo.channel(`games.public.${room().id}`) : echo.private(`games.${room().id}`);
        channelName = room().is_spectator ? `games.public.${room().id}` : `games.${room().id}`;
        channel.listen('.room.realtime.ping', (notice: Notice) => {
            if (room().is_spectator && notice.visibility !== 'public_view') {
                accessLost();
                return;
            }
            if (!gate.observe(notice.revision, room().current_revision)) return;
            reload();
        }).listen('.room.viewer_count.changed', (notice: { viewer_count: number }) => options.onViewerCount?.(notice.viewer_count))
            .subscribed(() => {
                status.value = 'connected';
                void check();
            }).error(() => {
                status.value = 'error';
                void check();
            });
    };
    const wake = () => { void check(); };
    const offline = () => { status.value = 'error'; syncError.value = true; };
    onMounted(() => {
        subscribe();
        if (echo) {
            const connection = echo.connector.pusher.connection;
            const stateChanged = ({ current }: { current: string }) => {
                status.value = current === 'connected' ? 'connected' : ['failed', 'unavailable', 'disconnected'].includes(current) ? 'error' : 'connecting';
                if (current === 'connected') void check();
            };
            connection.bind('state_change', stateChanged);
            unbind = () => connection.unbind('state_change', stateChanged);
        }
        window.addEventListener('online', wake);
        window.addEventListener('offline', offline);
        window.addEventListener('pageshow', wake);
        document.addEventListener('visibilitychange', wake);
        poll = window.setInterval(wake, 15000);
        wake();
    });
    watch(() => [room().id, room().is_spectator], () => {
        gate.target = room().current_revision;
        subscribe();
    });
    onUnmounted(() => {
        disposed = true;
        abort?.abort();
        leave();
        unbind?.();
        window.clearInterval(poll);
        window.removeEventListener('online', wake);
        window.removeEventListener('offline', offline);
        window.removeEventListener('pageshow', wake);
        document.removeEventListener('visibilitychange', wake);
    });

    return { status, syncError, resync: check };
}
