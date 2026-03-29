import './bootstrap';
import '@mantine/core/styles.css';
import '@mantine/code-highlight/styles.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { MantineProvider } from '@mantine/core';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <MantineProvider defaultColorScheme="light">
                <App {...props} />
            </MantineProvider>
        );
    },
});
