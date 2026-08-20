import { enqueue } from '../offline/queue.js';

const SYNC_ENDPOINT = '/kardex/entradas/sync';

function emptyState() {
    return {
        submitting: false,
        submitted: false,
        queuedOffline: false,
        errorMessage: '',
        warehouse_id: '',
        master_item_id: '',
        quantity: 1,
        lot_number: '',
        expiry_date: '',
        notes: '',
    };
}

export default function stockEntryForm() {
    return {
        ...emptyState(),

        buildPayload() {
            return {
                client_uuid: crypto.randomUUID(),
                warehouse_id: this.warehouse_id,
                master_item_id: this.master_item_id,
                quantity: this.quantity,
                lot_number: this.lot_number || null,
                expiry_date: this.expiry_date || null,
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
                        this.errorMessage = results[0]?.message ?? 'No se pudo guardar la entrada.';
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
