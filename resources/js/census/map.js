import L from 'leaflet';

// Centro aproximado del casco urbano de Roldanillo, Valle del Cauca — usado solo
// como punto de partida del mapa cuando el GPS del dispositivo falla o está indoor.
const ROLDANILLO_CENTER = [4.4144, -76.1536];

/**
 * Inicializa el mapa de respaldo para ubicar el pin manualmente cuando la
 * Geolocation API falla (sin señal GPS o permiso denegado).
 *
 * @param {HTMLElement} container
 * @param {(lat: number, lng: number) => void} onPick
 */
export function initFallbackMap(container, onPick) {
    const map = L.map(container).setView(ROLDANILLO_CENTER, 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    let marker = null;

    map.on('click', (event) => {
        const { lat, lng } = event.latlng;

        if (marker) {
            marker.setLatLng(event.latlng);
        } else {
            marker = L.marker(event.latlng).addTo(map);
        }

        onPick(lat, lng);
    });

    return map;
}
