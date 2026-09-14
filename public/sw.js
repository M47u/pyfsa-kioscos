// Service worker de la fase offline (documento de alcance: "Solo Ventas y
// Cobrar fiado funcionan offline — Service Worker + IndexedDB + UUID de
// dispositivo"; ver CLAUDE.md, arquitectura offline). Reemplaza al service
// worker no-op original (ver el comentario que este archivo tenía antes,
// ahora en el historial de git) que solo existía para cumplir el requisito
// técnico de "instalable" de los navegadores.
//
// Sigue siendo deliberadamente chico: la sincronización REAL (reintentar
// cada venta/pago pendiente contra el servidor cuando vuelve la señal) vive
// en resources/js/offline.js, corriendo en la página con un listener del
// evento 'online' del browser — no acá. La app real de este negocio se usa
// activamente en un mostrador (no una PWA cerrada en segundo plano), así
// que ese listener alcanza para el caso real sin necesitar la Background
// Sync API (soporte de navegador desparejo, complejidad extra para el
// mismo resultado).
//
// Lo que SÍ hace este service worker, y es nuevo respecto del no-op
// original: cachea el "app shell" (el HTML de /ventas/create y
// /clientes/{id}, más los assets de Vite que ya se pidieron) con una
// estrategia network-first — intenta la red primero, y si falla (offline
// real) sirve la última copia cacheada. Esto es lo que permite que un
// cajero pueda ABRIR /ventas/create sin conexión para cargar una venta
// nueva, no solo que una venta ya cargada en una pestaña abierta se pueda
// encolar. Sin este cacheo, offline.js nunca llegaría a ejecutar: el
// navegador ni siquiera podría cargar la página.
//
// A propósito NO cachea nada más (Productos, Reportes, Usuarios): esas
// pantallas no forman parte del alcance offline (ver documento de alcance)
// y network-first ya haría de más ahí sin ningún beneficio real.
const CACHE_VERSION = 'pyfsa-kioscos-offline-v1';

const RUTAS_APP_SHELL = [
    '/ventas/create',
];

self.addEventListener('install', (event) => {
    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(RUTAS_APP_SHELL).catch(() => {
            // Si el precache falla (ej. el usuario nunca estuvo logueado
            // todavía y estas rutas redirigen a /login), no rompe la
            // instalación del service worker: el cacheo network-first de
            // fetch de abajo va completando el cache de a poco con lo que
            // el usuario efectivamente visita.
        }))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((nombres) => Promise.all(
            nombres
                .filter((nombre) => nombre !== CACHE_VERSION)
                .map((nombre) => caches.delete(nombre))
        )).then(() => self.clients.claim())
    );
});

function esRutaCacheable(url) {
    // Vite emite assets versionados por hash bajo /build/ — network-first
    // funciona igual para esos (siempre intenta la red primero), pero acá
    // filtramos qué DOCUMENTOS de navegación (HTML) valen la pena cachear:
    // únicamente las dos pantallas con una ACCIÓN offline real (documento de
    // alcance) — cargar una venta nueva y cobrar fiado. /ventas (el listado)
    // queda afuera a propósito: es solo lectura, no hace falta que abra sin
    // conexión.
    return url.pathname === '/ventas/create'
        || /^\/clientes\/\d+$/.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Solo GET: nunca cachear ni interceptar un POST (las ventas/pagos
    // siempre van directo a la red o a la cola de IndexedDB, jamás al
    // Cache Storage — ver resources/js/offline.js).
    if (event.request.method !== 'GET') {
        return;
    }

    const esAssetDeVite = url.pathname.startsWith('/build/');
    const esAppShell = url.origin === self.location.origin && esRutaCacheable(url);

    if (!esAssetDeVite && !esAppShell) {
        return; // deja pasar a la red tal cual, sin cachear ni interceptar
    }

    event.respondWith(
        fetch(event.request)
            .then((respuesta) => {
                // Solo cachea respuestas 200 reales — nunca un redirect ni un
                // error, para no servir después una versión rota.
                if (respuesta.ok) {
                    const copia = respuesta.clone();
                    caches.open(CACHE_VERSION).then((cache) => cache.put(event.request, copia));
                }

                return respuesta;
            })
            .catch(() => caches.match(event.request))
    );
});
