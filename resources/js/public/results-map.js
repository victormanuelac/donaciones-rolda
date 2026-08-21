import L from 'leaflet';

const ROLDANILLO_CENTER = [4.4144, -76.1536];

/**
 * Mapa de resultados de búsqueda pública: agrupa las tarjetas visibles por
 * bodega y dibuja un marcador con popup por cada una. Se redibuja al recibir
 * el evento `public-search:results-updated` (en vez de leer el estado del
 * componente padre por herencia de scope de Alpine, más frágil de verificar).
 */
export default function publicResultsMap() {
    return {
        map: null,
        markers: [],
        onResultsUpdated: null,

        init() {
            this.map = L.map(this.$refs.mapContainer).setView(ROLDANILLO_CENTER, 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);

            this.onResultsUpdated = (event) => this.redraw(event.detail);
            window.addEventListener('public-search:results-updated', this.onResultsUpdated);
        },

        /**
         * Alpine llama a `destroy()` al desmontar el componente. Sin esto, el
         * listener global y la instancia de Leaflet (con sus capas de tiles)
         * quedan en memoria en cada navegación — ver
         * docs/17-Auditoria-Frontend.md, hallazgo M-8.
         */
        destroy() {
            if (this.onResultsUpdated) {
                window.removeEventListener('public-search:results-updated', this.onResultsUpdated);
                this.onResultsUpdated = null;
            }

            this.markers.forEach((marker) => marker.remove());
            this.markers = [];

            this.map?.remove();
            this.map = null;
        },

        redraw(results) {
            this.markers.forEach((marker) => marker.remove());
            this.markers = [];

            const byWarehouse = new Map();

            for (const item of results) {
                for (const location of item.locations) {
                    if (location.latitude === null || location.longitude === null) {
                        continue;
                    }

                    if (!byWarehouse.has(location.warehouse_id)) {
                        byWarehouse.set(location.warehouse_id, { name: location.warehouse_name, lat: location.latitude, lng: location.longitude, items: [] });
                    }

                    byWarehouse.get(location.warehouse_id).items.push({
                        item_name: item.item_name,
                        availability_emoji: location.availability_emoji,
                    });
                }
            }

            for (const warehouse of byWarehouse.values()) {
                const marker = L.marker([warehouse.lat, warehouse.lng])
                    .addTo(this.map)
                    .bindPopup(this.buildPopup(warehouse));

                this.markers.push(marker);
            }
        },

        /**
         * El popup se arma con nodos del DOM y `textContent`, nunca concatenando
         * HTML: `bindPopup()` interpreta las cadenas como HTML, y tanto el nombre
         * de la bodega como el del ítem son texto que escribe una persona (un
         * operador puede crear ítems nuevos desde el Kardex, Módulo 4). Con
         * interpolación esto era un XSS almacenado sobre el portal público, que
         * es anónimo — ver docs/17-Auditoria-Frontend.md, hallazgo C-1.
         *
         * @param {{name: string, items: Array<{item_name: string, availability_emoji: string}>}} warehouse
         * @returns {HTMLElement}
         */
        buildPopup(warehouse) {
            const container = document.createElement('div');

            const title = document.createElement('strong');
            title.textContent = warehouse.name;
            container.append(title);

            for (const item of warehouse.items) {
                container.append(document.createElement('br'));
                container.append(document.createTextNode(`${item.availability_emoji} ${item.item_name}`));
            }

            return container;
        },
    };
}
