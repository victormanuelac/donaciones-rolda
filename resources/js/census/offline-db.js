import Dexie from 'dexie';

/**
 * IndexedDB local para el censo en campo, sin conexión. Sigue la convención de
 * `sync_queue` documentada en CLAUDE.md (arquitectura general / PWA offline-first):
 * cada captura pendiente se guarda con su `client_uuid` y se reintenta hasta que
 * el servidor la confirme.
 */
export const db = new Dexie('donaciones-rolda-censo');

db.version(1).stores({
    sync_queue: 'client_uuid, status, created_at',
});

/**
 * @param {string} clientUuid
 * @param {object} payload
 */
export async function queueCensusEntry(clientUuid, payload) {
    await db.sync_queue.put({
        client_uuid: clientUuid,
        payload,
        status: 'pending',
        created_at: new Date().toISOString(),
    });
}

export async function pendingCensusEntries() {
    return db.sync_queue.where('status').equals('pending').toArray();
}

export async function removeCensusEntry(clientUuid) {
    await db.sync_queue.delete(clientUuid);
}

export async function pendingCount() {
    return db.sync_queue.where('status').equals('pending').count();
}
