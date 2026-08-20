import { pendingEntries, removeEntry } from './queue.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postBatch(endpoint, payloads) {
    const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ entries: payloads }),
    });

    if (!response.ok) {
        return null;
    }

    return response.json();
}

/**
 * Envía al servidor todos los elementos pendientes en la cola local, agrupados por
 * endpoint. Se llama al cargar la página (si hay señal) y en cada evento `online` —
 * es el mecanismo compatible con todos los navegadores (Background Sync de Service
 * Worker no está soportado en Safari/iOS, muy usado en campo).
 *
 * @returns {Promise<{synced: number, failed: number}>}
 */
export async function flushQueue() {
    const pending = await pendingEntries();

    if (pending.length === 0) {
        return { synced: 0, failed: 0 };
    }

    const byEndpoint = pending.reduce((groups, item) => {
        (groups[item.endpoint] ??= []).push(item);

        return groups;
    }, /** @type {Record<string, typeof pending>} */ ({}));

    let synced = 0;
    let failed = 0;

    for (const [endpoint, items] of Object.entries(byEndpoint)) {
        try {
            const result = await postBatch(endpoint, items.map((item) => item.payload));

            if (result === null) {
                failed += items.length;
                continue;
            }

            for (const entryResult of result.results) {
                if (entryResult.status === 'ok') {
                    await removeEntry(entryResult.client_uuid);
                    synced += 1;
                } else {
                    failed += 1;
                }
            }
        } catch {
            // Sigue sin conexión: los elementos se quedan en la cola para el próximo intento.
            failed += items.length;
        }
    }

    return { synced, failed };
}

export function watchConnectivity(onSync) {
    window.addEventListener('online', async () => onSync(await flushQueue()));

    if (navigator.onLine) {
        flushQueue().then(onSync);
    }
}
