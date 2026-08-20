const CACHE_NAME = 'donaciones-rolda-shell-v1';

// Estrategia "network falling back to cache": intenta red primero (para no servir
// una versión vieja del formulario mientras hay señal) y cachea cada respuesta
// exitosa; si la red falla (sin señal en campo), sirve la última versión cacheada.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));

                return response;
            })
            .catch(() => caches.match(event.request))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        ))
    );
});
