import { pendingCensusEntries, removeCensusEntry } from './offline-db.js';

/**
 * Envía al servidor todas las capturas pendientes en la cola local. Se llama al cargar
 * la página (si hay señal) y cada vez que el navegador dispara el evento `online` — es el
 * mecanismo de sincronización compatible con todos los navegadores (Background Sync de
 * Service Worker no está soportado en Safari/iOS, muy usado en campo).
 *
 * @returns {Promise<{synced: number, failed: number}>}
 */
export async function flushCensusQueue() {
    const pending = await pendingCensusEntries();

    if (pending.length === 0) {
        return { synced: 0, failed: 0 };
    }

    let synced = 0;
    let failed = 0;

    try {
        const response = await fetch('/censo/sync', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ entries: pending.map((item) => item.payload) }),
        });

        if (!response.ok) {
            return { synced: 0, failed: pending.length };
        }

        const { results } = await response.json();

        for (const result of results) {
            if (result.status === 'ok') {
                await removeCensusEntry(result.client_uuid);
                synced += 1;
            } else {
                failed += 1;
            }
        }
    } catch {
        // Sigue sin conexión: los elementos se quedan en la cola para el próximo intento.
        return { synced: 0, failed: pending.length };
    }

    return { synced, failed };
}

export function watchConnectivity(onSync) {
    window.addEventListener('online', async () => onSync(await flushCensusQueue()));

    if (navigator.onLine) {
        flushCensusQueue().then(onSync);
    }
}
