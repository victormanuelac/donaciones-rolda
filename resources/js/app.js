import 'leaflet/dist/leaflet.css';
import censusWizard from './census/wizard.js';
import { initFallbackMap } from './census/map.js';
import stockEntryForm from './kardex/entry-form.js';
import stockExitForm from './kardex/exit-form.js';
import { watchConnectivity } from './offline/sync.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('censusWizard', censusWizard);
    window.Alpine.data('censusFallbackMap', () => ({
        init() {
            initFallbackMap(this.$refs.mapContainer, (lat, lng) => this.setManualPin(lat, lng));
        },
    }));
    window.Alpine.data('stockEntryForm', stockEntryForm);
    window.Alpine.data('stockExitForm', stockExitForm);
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker los formularios siguen funcionando en línea; solo se
            // pierde la posibilidad de abrirlos sin conexión.
        });
    });
}

window.addEventListener('DOMContentLoaded', () => {
    watchConnectivity(() => {
        window.dispatchEvent(new CustomEvent('offline-queue:sync-completado'));
    });
});
