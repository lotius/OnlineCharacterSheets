import { echo } from '@/echo';
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { decide } from './syncDecision';
import type { Character } from './types';

type Notice = {
    revision: number;
    visibility: string;
    character_id: number | null;
    character_revision: number | null;
};

type Options = {
    gameId: () => number;
    isSpectator: () => boolean;
    /** Revision of the props from the last full (Inertia) load. */
    revision: () => number;
    store: {
        server: Record<number, Character>;
        refresh: (id: number) => Promise<void>;
    };
    /** Inertia props to reload when the change is not a single known character. */
    only: string[];
    onViewerCount?: (count: number) => void;
};

/**
 * Realtime notices are data-free. A notice for exactly the next revision that names one character
 * refreshes only that character (or nothing, if our own mutation response already brought it);
 * anything else (gaps, room-level changes, reconnects) reloads the page props.
 */
export function useLiveSync(options: Options) {
    const status = ref<'connecting' | 'connected' | 'error'>('connecting');
    const syncError = ref(false);
    let applied = options.revision();
    let reloading = false;
    let reloadAgain = false;
    let disposed = false;
    let channelName: string | null = null;
    let poll: number | undefined;
    let unbind: (() => void) | null = null;

    watch(options.revision, (revision) => {
        applied = Math.max(applied, revision);
    });

    const leave = () => {
        if (channelName) echo?.leave(channelName);
        channelName = null;
    };

    const reload = () => {
        if (disposed || !navigator.onLine) return;
        if (reloading) {
            reloadAgain = true;
            return;
        }
        reloading = true;
        let succeeded = false;
        router.reload({
            only: options.only,
            onSuccess: () => {
                succeeded = true;
                syncError.value = false;
            },
            onFinish: () => {
                reloading = false;
                if (!succeeded) syncError.value = true;
                if (reloadAgain && !disposed) {
                    reloadAgain = false;
                    window.setTimeout(reload, 50);
                }
            },
        });
    };

    const handle = (notice: Notice) => {
        const known =
            notice.character_id !== null
                ? options.store.server[notice.character_id]
                : undefined;
        const decision = decide(notice, applied, known?.revision ?? null);
        if (decision === 'ignore') return;
        if (decision === 'reload') {
            reload();
            return;
        }
        applied = notice.revision;
        if (decision === 'refresh-character' && known)
            void options.store.refresh(known.id);
    };

    const check = async () => {
        if (disposed || !navigator.onLine || document.hidden) return;
        try {
            const response = await fetch(
                `/games/${options.gameId()}/revision`,
                {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                },
            );
            if ([401, 403, 404].includes(response.status)) {
                leave();
                router.visit('/rooms');
                return;
            }
            if (!response.ok) throw new Error('Revision check failed');
            const state = (await response.json()) as {
                revision: number;
                is_spectator: boolean;
            };
            if (
                state.revision !== applied ||
                state.is_spectator !== options.isSpectator() ||
                syncError.value
            )
                reload();
        } catch {
            if (!disposed) syncError.value = true;
        }
    };

    const subscribe = () => {
        leave();
        if (!echo) {
            status.value = 'error';
            return;
        }
        const spectator = options.isSpectator();
        channelName = spectator
            ? `games.public.${options.gameId()}`
            : `games.${options.gameId()}`;
        const channel = spectator
            ? echo.channel(channelName)
            : echo.private(channelName);
        channel
            .listen('.room.realtime.ping', (notice: Notice) => {
                if (spectator && notice.visibility !== 'public_view') {
                    leave();
                    router.visit(`/rooms`);
                    return;
                }
                handle(notice);
            })
            .listen(
                '.room.viewer_count.changed',
                (notice: { viewer_count: number }) =>
                    options.onViewerCount?.(notice.viewer_count),
            )
            .subscribed(() => {
                status.value = 'connected';
                void check();
            })
            .error(() => {
                status.value = 'error';
                void check();
            });
    };

    const wake = () => void check();
    const offline = () => {
        status.value = 'error';
        syncError.value = true;
    };

    onMounted(() => {
        subscribe();
        if (echo) {
            const connection = echo.connector.pusher.connection;
            const stateChanged = ({ current }: { current: string }) => {
                status.value =
                    current === 'connected'
                        ? 'connected'
                        : ['failed', 'unavailable', 'disconnected'].includes(
                                current,
                            )
                          ? 'error'
                          : 'connecting';
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
    });

    watch(() => [options.gameId(), options.isSpectator()], subscribe);

    onUnmounted(() => {
        disposed = true;
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
