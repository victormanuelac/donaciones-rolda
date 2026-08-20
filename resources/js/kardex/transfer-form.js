import { enqueue } from '../offline/queue.js';

const SYNC_ENDPOINT = '/kardex/traslados/sync';

function emptyState() {
    return {
        submitting: false,
        submitted: false,
        queuedOffline: false,
        errorMessage: '',
        source_warehouse_id: '',
        stock_entry_id: '',
        destination_warehouse_id: '',
        quantity: 1,
        notes: '',
    };
}

/**
 * @param {Record<string, number>} warehouseByStockEntry Mapa id de stock_entry -> id de bodega de origen.
 * @param {Array<number>} fefoOrderedStockEntryIds Ids de lotes disponibles en orden FEFO.
 */
export default function stockTransferForm(warehouseByStockEntry, fefoOrderedStockEntryIds) {
    return {
        ...emptyState(),
        warehouseByStockEntry,

        init() {
            if (fefoOrderedStockEntryIds.length > 0) {
                this.stock_entry_id = fefoOrderedStockEntryIds[0];
                this.onStockEntryChange();
            }
        },

        onStockEntryChange() {
            this.source_warehouse_id = this.warehouseByStockEntry[this.stock_entry_id] ?? '';
        },

        buildPayload() {
            return {
                client_uuid: crypto.randomUUID(),
                stock_entry_id: this.stock_entry_id,
                source_warehouse_id: this.source_warehouse_id,
                destination_warehouse_id: this.destination_warehouse_id,
                quantity: this.quantity,
                notes: this.notes || null,
            };
        },

        async submit() {
            this.submitting = true;
            this.errorMessage = '';

            const payload = this.buildPayload();

            try {
                const response = await fetch(SYNC_ENDPOINT, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ entries: [payload] }),
                });

                if (response.ok) {
                    const { results } = await response.json();

                    if (results[0]?.status === 'ok') {
                        this.submitted = true;
                    } else {
                        this.errorMessage = results[0]?.message ?? 'No se pudo guardar el traslado.';
                    }
                } else if (response.status === 422) {
                    const body = await response.json();
                    this.errorMessage = Object.values(body.errors ?? {}).flat().join(' ') || 'Revisa los datos del formulario.';
                } else {
                    await this.queueOffline(payload.client_uuid, payload);
                }
            } catch {
                await this.queueOffline(payload.client_uuid, payload);
            } finally {
                this.submitting = false;
            }
        },

        async queueOffline(clientUuid, payload) {
            await enqueue(SYNC_ENDPOINT, clientUuid, payload);
            this.queuedOffline = true;
            this.submitted = true;
        },

        startNew() {
            Object.assign(this, emptyState());
        },
    };
}
