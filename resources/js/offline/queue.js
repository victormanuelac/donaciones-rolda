import Dexie from 'dexie';

/**
 * Cola offline compartida (IndexedDB/Dexie) para cualquier formulario de campo:
 * censo de hogares, entradas/salidas de Kardex, etc. Sigue la convención de
 * `sync_queue` con `uuid` documentada en CLAUDE.md (arquitectura / PWA offline-first).
 * Cada elemento guarda a qué endpoint debe enviarse para que un único mecanismo de
 * sincronización sirva a todos los formularios.
 */
export const db = new Dexie('donaciones-rolda-offline');

db.version(1).stores({
    sync_queue: 'client_uuid, endpoint, status, created_at',
});

/**
 * @param {string} endpoint
 * @param {string} clientUuid
 * @param {object} payload
 */
export async function enqueue(endpoint, clientUuid, payload) {
    await db.sync_queue.put({
        client_uuid: clientUuid,
        endpoint,
        payload,
        status: 'pending',
        created_at: new Date().toISOString(),
    });
}

export async function pendingEntries() {
    return db.sync_queue.where('status').equals('pending').toArray();
}

export async function removeEntry(clientUuid) {
    await db.sync_queue.delete(clientUuid);
}

export async function pendingCount() {
    return db.sync_queue.where('status').equals('pending').count();
}
