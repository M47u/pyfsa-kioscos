// Service worker mínimo, a propósito sin caché.
//
// El único motivo de que exista es cumplir el requisito técnico de
// "instalable" de los navegadores (Chrome/Android exige un service worker
// registrado con un listener de 'fetch' para ofrecer el prompt de
// instalar). Deliberadamente NO cachea nada ni responde nada distinto de
// lo que pediría la red: el documento de alcance define el offline real
// (Service Worker + IndexedDB + UUID de dispositivo) como una fase aparte,
// limitada a Ventas y Cobrar fiado — ver sección 04 y el gotcha
// correspondiente en CLAUDE.md. Meterle caché acá "de paso" adelantaría
// esa fase a medias y sin la lógica de sincronización que necesita.
//
// Cuando se implemente esa fase, este archivo es el que se reemplaza.
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// Sin este listener el navegador no lo cuenta como "controla la página" a
// los efectos de instalabilidad, aunque no llame respondWith(): dejar que
// la red responda como si el service worker no existiera.
self.addEventListener('fetch', () => {});
