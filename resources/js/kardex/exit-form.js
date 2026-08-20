import { enqueue } from '../offline/queue.js';

const SYNC_ENDPOINT = '/kardex/salidas/sync';

function emptyState() {
    return {
        submitting: false,
        submitted: false,
        queuedOffline: false,
        errorMessage: '',
        warehouse_id: '',
        stock_entry_id: '',
        quantity_released: 1,
        exit_reason: '',
        received_by_name: '',
        destination_description: '',
        notes: '',
    };
}

/**
 * @param {Record<string, number>} warehouseByStockEntry Mapa id de stock_entry -> id de bodega,
 *   para completar warehouse_id automáticamente al elegir el lote a despachar.
 */
export default function stockExitForm(warehouseByStockEntry) {
    return {
        ...emptyState(),
        warehouseByStockEntry,

        onStockEntryChange() {
            this.warehouse_id = this.warehouseByStockEntry[this.stock_entry_id] ?? '';
        },

        buildPayload() {
            return {
                client_uuid: crypto.randomUUID(),
                warehouse_id: this.warehouse_id,
                stock_entry_id: this.stock_entry_id,
                quantity_released: this.quantity_released,
                exit_reason: this.exit_reason,
                received_by_name: this.received_by_name || null,
                destination_description: this.destination_description || null,
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
                        this.errorMessage = results[0]?.message ?? 'No se pudo guardar la salida.';
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
