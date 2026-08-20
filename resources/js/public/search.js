let debounceTimer = null;

const EARTH_RADIUS_KM = 6371;

function haversineKm(lat1, lng1, lat2, lng2) {
    const toRad = (deg) => (deg * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;

    return EARTH_RADIUS_KM * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

/**
 * Las opciones de categoría/zona se renderizan en el servidor (Blade), este
 * componente solo maneja el estado de los filtros y los resultados.
 */
export default function publicSearch(zones = []) {
    return {
        zones,
        query: '',
        categoryId: '',
        zoneId: '',
        results: [],
        loading: false,
        searched: false,
        userLocation: null,
        locationLabel: '',
        locating: false,

        // Modal de contacto
        contactResult: null,
        contactErrorMessage: '',
        contactSubmitting: false,
        selectedWarehouseId: null,
        selectedWarehouseName: '',
        turnstileWidgetId: null,
        turnstileToken: '',

        init() {
            // A propósito no se busca nada al cargar: la lista de insumos solo debe
            // aparecer después de que la persona busque o aplique un filtro.
        },

        debouncedSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => this.runSearch(), 350);
        },

        useMyLocation() {
            if (!('geolocation' in navigator)) {
                return;
            }

            this.locating = true;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.userLocation = { lat: position.coords.latitude, lng: position.coords.longitude };
                    this.locationLabel = this.nearestZoneLabel(this.userLocation);
                    this.locating = false;
                    this.runSearch();
                },
                () => {
                    this.locating = false;
                },
                { enableHighAccuracy: true, timeout: 15000 }
            );
        },

        nearestZoneLabel(location) {
            const zonesWithCoords = this.zones.filter((zone) => zone.latitude !== null && zone.longitude !== null);

            if (zonesWithCoords.length === 0) {
                return 'Tu ubicación';
            }

            const nearest = zonesWithCoords.reduce((closest, zone) => {
                const distance = haversineKm(location.lat, location.lng, Number(zone.latitude), Number(zone.longitude));

                return !closest || distance < closest.distance ? { zone, distance } : closest;
            }, null);

            return nearest.zone.name;
        },

        clearLocation() {
            this.userLocation = null;
            this.locationLabel = '';
            this.runSearch();
        },

        async runSearch() {
            this.loading = true;

            const params = new URLSearchParams();
            if (this.query) params.set('q', this.query);
            if (this.categoryId) params.set('category_id', this.categoryId);
            if (this.zoneId) params.set('zone_id', this.zoneId);
            if (this.userLocation) {
                params.set('lat', this.userLocation.lat);
                params.set('lng', this.userLocation.lng);
            }

            try {
                const response = await fetch(`/api/public/search?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                });

                if (response.ok) {
                    const body = await response.json();
                    this.results = body.results;
                    window.dispatchEvent(new CustomEvent('public-search:results-updated', { detail: this.results }));
                }
            } finally {
                this.loading = false;
                this.searched = true;
            }
        },

        openContact(warehouseId, warehouseName) {
            this.selectedWarehouseId = warehouseId;
            this.selectedWarehouseName = warehouseName;
            this.contactResult = null;
            this.contactErrorMessage = '';
            this.turnstileToken = '';
            this.$flux.modal('contact-unlock-modal').show();

            this.$nextTick(() => this.renderTurnstile());
        },

        closeContact() {
            this.$flux.modal('contact-unlock-modal').close();

            if (this.turnstileWidgetId !== null && window.turnstile) {
                window.turnstile.remove(this.turnstileWidgetId);
                this.turnstileWidgetId = null;
            }
        },

        renderTurnstile() {
            if (!window.turnstile || !this.$refs.turnstileContainer) {
                return;
            }

            this.turnstileWidgetId = window.turnstile.render(this.$refs.turnstileContainer, {
                sitekey: this.$refs.turnstileContainer.dataset.sitekey,
                callback: (token) => {
                    this.turnstileToken = token;
                },
            });
        },

        async submitContactUnlock() {
            if (!this.turnstileToken) {
                this.contactErrorMessage = 'Completa la verificación antes de continuar.';

                return;
            }

            this.contactSubmitting = true;
            this.contactErrorMessage = '';

            try {
                const response = await fetch('/api/public/contact-unlock', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        warehouse_id: this.selectedWarehouseId,
                        turnstile_token: this.turnstileToken,
                    }),
                });

                const body = await response.json();

                if (response.ok) {
                    this.contactResult = body;
                } else {
                    this.contactErrorMessage = body.message ?? 'No se pudo validar la verificación.';
                }
            } catch {
                this.contactErrorMessage = 'No se pudo conectar. Verifica tu conexión e intenta de nuevo.';
            } finally {
                this.contactSubmitting = false;
            }
        },
    };
}
