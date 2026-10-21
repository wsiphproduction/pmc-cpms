import '../css/app.css';
import 'bootstrap';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { syncFontScale } from './lib/font-scale';

type AuthProps = { auth?: { user?: { id: number; font_scale?: number | null } | null } };

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // The user's text size, on every page — and anew whenever the
        // signed-in account changes.
        syncFontScale((props.initialPage.props as AuthProps).auth?.user);
        router.on('navigate', event => syncFontScale((event.detail.page.props as AuthProps).auth?.user));

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
