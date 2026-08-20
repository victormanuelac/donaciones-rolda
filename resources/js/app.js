import 'leaflet/dist/leaflet.css';
import censusWizard from './census/wizard.js';
import { watchConnectivity } from './census/sync.js';
import { initFallbackMap } from './census/map.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('censusWizard', censusWizard);
    window.Alpine.data('censusFallbackMap', () => ({
        init() {
            initFallbackMap(this.$refs.mapContainer, (lat, lng) => this.setManualPin(lat, lng));
        },
    }));
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker el formulario sigue funcionando en línea; solo se
            // pierde la posibilidad de abrir la página ya visitada sin conexión.
        });
    });
}

window.addEventListener('DOMContentLoaded', () => {
    watchConnectivity(() => {
        window.dispatchEvent(new CustomEvent('censo:sync-completado'));
    });
});
