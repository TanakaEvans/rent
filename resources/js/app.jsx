import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import { route as ziggyRoute } from 'ziggy-js';

// Make route function available globally
window.route = (name, params, absolute) => {
  return ziggyRoute(name, params, absolute, window.Ziggy);
};

createInertiaApp({
  title: (title) => `${title} - Dzimba`,
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true })
    return pages[`./Pages/${name}.jsx`]
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
  progress: {
    color: '#0ea372',
    delay: 250,
    includeCSS: true,
    showSpinner: true,
  },
})
