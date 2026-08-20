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

        init() {
            this.map = L.map(this.$refs.mapContainer).setView(ROLDANILLO_CENTER, 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);

            window.addEventListener('public-search:results-updated', (event) => this.redraw(event.detail));
        },

        redraw(results) {
            this.markers.forEach((marker) => marker.remove());
            this.markers = [];

            const byWarehouse = new Map();

            for (const item of results) {
                if (item.latitude === null || item.longitude === null) {
                    continue;
                }

                if (!byWarehouse.has(item.warehouse_id)) {
                    byWarehouse.set(item.warehouse_id, { name: item.warehouse_name, lat: item.latitude, lng: item.longitude, items: [] });
                }

                byWarehouse.get(item.warehouse_id).items.push(item);
            }

            for (const warehouse of byWarehouse.values()) {
                const itemsList = warehouse.items
                    .map((item) => `${item.availability_emoji} ${item.item_name}`)
                    .join('<br>');

                const marker = L.marker([warehouse.lat, warehouse.lng])
                    .addTo(this.map)
                    .bindPopup(`<strong>${warehouse.name}</strong><br>${itemsList}`);

                this.markers.push(marker);
            }
        },
    };
}
