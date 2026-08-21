/**
 * `crypto.randomUUID()` solo existe en contextos seguros (HTTPS o localhost).
 * El ambiente de pruebas en EC2 se sirve por HTTP plano sobre IP, así que ahí
 * es `undefined` y cualquier llamada directa lanza — dejando el formulario
 * colgado en "Guardando..." sin encolar nada. Ver docs/17-Auditoria-Frontend.md,
 * hallazgo C-2.
 *
 * El respaldo no pretende ser criptográficamente fuerte: el `client_uuid` solo
 * necesita ser único por dispositivo para que el servidor deduplique la cola.
 *
 * @returns {string}
 */
export function newClientUuid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now().toString(16)}-${Math.random().toString(16).slice(2, 10)}-${Math.random().toString(16).slice(2, 10)}`;
}

/**
 * Un contexto no seguro además deshabilita el Service Worker, la Cache API y la
 * Geolocation API. Sirve para avisar en vez de fallar en silencio.
 *
 * @returns {boolean}
 */
export function isSecureContext() {
    return window.isSecureContext === true;
}
