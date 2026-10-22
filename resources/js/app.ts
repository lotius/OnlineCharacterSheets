import { createInertiaApp } from '@inertiajs/vue3';
import './echo';

const appName = import.meta.env.VITE_APP_NAME || 'Online Character Sheets';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});
