import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { store } from '@/Utils/fileUtils.ts';
import {Provider} from "react-redux";
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';


await createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({el, App, props}) {
        const root = createRoot(el);

        root.render(
            <Provider store={store}>
                    <App {...props} />
            </Provider>)
    },
    progress: {
        color: '#4B5563',
    },
});
