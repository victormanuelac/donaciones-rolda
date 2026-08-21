import 'leaflet/dist/leaflet.css';
import censusWizard from './census/wizard.js';
import { initFallbackMap } from './census/map.js';
import stockEntryForm from './kardex/entry-form.js';
import stockExitForm from './kardex/exit-form.js';
import stockTransferForm from './kardex/transfer-form.js';
import publicSearch from './public/search.js';
import publicResultsMap from './public/results-map.js';
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
    window.Alpine.data('stockTransferForm', stockTransferForm);
    window.Alpine.data('publicSearch', publicSearch);
    window.Alpine.data('publicResultsMap', publicResultsMap);
});

/**
 * El Service Worker (y con él toda la caché del shell offline) solo existe en
 * contextos seguros. En HTTP plano `navigator.serviceWorker` ni siquiera está
 * definido, así que el `.catch()` nunca se disparaba y la app quedaba sin modo
 * offline en absoluto, en silencio. Ahora se avisa por consola y se expone
 * `window.offlineShellDisponible` para que la UI pueda reaccionar —
 * ver docs/17-Auditoria-Frontend.md, hallazgo C-2.
 */
window.offlineShellDisponible = false;

function registrarServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        console.warn(
            window.isSecureContext
                ? '[offline] Este navegador no soporta Service Worker: la app no funcionará sin conexión.'
                : '[offline] La app se está sirviendo por HTTP (contexto no seguro): el Service Worker, la Cache API y el GPS quedan deshabilitados. Sirve la app por HTTPS para habilitar el modo sin conexión.'
        );

        return;
    }

    navigator.serviceWorker
        .register('/sw.js')
        .then(() => {
            window.offlineShellDisponible = true;
        })
        .catch((error) => {
            console.error('[offline] No se pudo registrar el Service Worker: la app no funcionará sin conexión.', error);
        });
}

window.addEventListener('load', registrarServiceWorker);

window.addEventListener('DOMContentLoaded', () => {
    watchConnectivity(() => {
        window.dispatchEvent(new CustomEvent('offline-queue:sync-completado'));
    });
});
