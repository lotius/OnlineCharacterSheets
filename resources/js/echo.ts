import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const scheme = String(import.meta.env.VITE_REVERB_SCHEME || 'http');

export const echo = new Echo({
    broadcaster: 'reverb',
    key: String(import.meta.env.VITE_REVERB_APP_KEY),
    wsHost: String(
        import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    ),
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
