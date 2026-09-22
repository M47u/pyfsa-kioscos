/**
 * Cola offline de "Ventas" y "Cobrar fiado" (documento de alcance: son los
 * dos únicos flujos que funcionan offline — ver CLAUDE.md, arquitectura
 * offline). No usa la Background Sync API del Service Worker a propósito
 * (soporte de navegador desparejo, complejidad extra para un caso de uso
 * real donde el dispositivo se usa activamente en el mostrador, no una app
 * cerrada en segundo plano): un listener de 'online' a nivel de página
 * alcanza.
 *
 * Por qué es seguro reintentar sin duplicar (ver el documento de alcance):
 * stock y saldo NUNCA se editan, se calculan de un historial de
 * movimientos — cada venta/pago offline es un INSERT nuevo, nunca una
 * edición. La idempotencia real la garantiza uuid_dispositivo (columna
 * UNIQUE en ventas/pagos, ver las migraciones y VentaController::store /
 * ClienteController::registrarPago): mandar el mismo item dos veces nunca
 * crea una fila dos veces, así que esta cola puede reintentar sin
 * cuidado especial de "ya lo mandé, no lo mande de nuevo" — el peor caso de
 * un reintento de más es un no-op del lado del servidor.
 *
 * Expone window.offlineSync para que los <script> inline de las vistas
 * (ventas/create.blade.php, clientes/show.blade.php, layouts/nav.blade.php)
 * lo usen sin necesidad de import — esos scripts son clásicos, no módulos.
 */

const DB_NOMBRE = 'pyfsa_kioscos_offline';
// v2: agrega STORE_PRODUCTOS (catálogo cacheado para buscar productos sin
// conexión — ver sincronizarCatalogoProductos()/buscarEnCatalogoLocal() más
// abajo). Bump obligatorio: onupgradeneeded solo corre si la versión pedida
// es mayor a la que ya tiene el navegador guardada de una sesión anterior.
const DB_VERSION = 2;
const STORE_VENTAS = 'ventas_pendientes';
const STORE_PAGOS = 'pagos_pendientes';
const STORE_PRODUCTOS = 'productos_cache';

let dbPromise = null;

function abrirDb() {
    if (dbPromise) {
        return dbPromise;
    }

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NOMBRE, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(STORE_VENTAS)) {
                db.createObjectStore(STORE_VENTAS, { keyPath: 'uuid_dispositivo' });
            }
            if (!db.objectStoreNames.contains(STORE_PAGOS)) {
                db.createObjectStore(STORE_PAGOS, { keyPath: 'uuid_dispositivo' });
            }
            if (!db.objectStoreNames.contains(STORE_PRODUCTOS)) {
                db.createObjectStore(STORE_PRODUCTOS, { keyPath: 'id' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
}

function reemplazarTodos(nombreStore, items) {
    return abrirDb().then((db) => new Promise((resolve, reject) => {
        const tx = db.transaction(nombreStore, 'readwrite');
        const store = tx.objectStore(nombreStore);
        store.clear();
        items.forEach((item) => store.put(item));
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    }));
}

function guardar(nombreStore, item) {
    return abrirDb().then((db) => new Promise((resolve, reject) => {
        const tx = db.transaction(nombreStore, 'readwrite');
        tx.objectStore(nombreStore).put(item);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    }));
}

function listar(nombreStore) {
    return abrirDb().then((db) => new Promise((resolve, reject) => {
        const tx = db.transaction(nombreStore, 'readonly');
        const request = tx.objectStore(nombreStore).getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    }));
}

function eliminar(nombreStore, uuidDispositivo) {
    return abrirDb().then((db) => new Promise((resolve, reject) => {
        const tx = db.transaction(nombreStore, 'readwrite');
        tx.objectStore(nombreStore).delete(uuidDispositivo);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    }));
}

/**
 * Convierte un <form> en una lista de pares [nombre, valor] — estructura
 * simple y 100% clonable por IndexedDB (a diferencia de un FormData, cuyo
 * soporte de structured clone es desparejo entre navegadores). Se
 * reconstruye a un FormData recién al momento de mandar el fetch, ver
 * armarFormData() más abajo.
 *
 * @param {HTMLFormElement} form
 * @returns {Array<[string, string]>}
 */
function serializarForm(form) {
    return Array.from(new FormData(form).entries()).map(([clave, valor]) => [clave, String(valor)]);
}

function armarFormData(entradas) {
    const formData = new FormData();
    entradas.forEach(([clave, valor]) => formData.append(clave, valor));
    return formData;
}

/**
 * Notifica a cualquier vista escuchando (hoy: el indicador del nav) que la
 * cola cambió, para que actualice su contador sin tener que hacer polling.
 */
function notificarCambioCola() {
    contarPendientes().then((cantidad) => {
        window.dispatchEvent(new CustomEvent('offlinequeuechange', { detail: { cantidad } }));
    });
}

/**
 * Cuenta total de ventas + pagos todavía sin sincronizar.
 *
 * @returns {Promise<number>}
 */
export async function contarPendientes() {
    const [ventas, pagos] = await Promise.all([listar(STORE_VENTAS), listar(STORE_PAGOS)]);
    return ventas.length + pagos.length;
}

/**
 * Punto de entrada único para los dos formularios offline-aware (venta y
 * pago): agrega (o reusa) el uuid_dispositivo del form, y:
 *
 * 1. Si el navegador ya sabe que está offline, ni intenta la red: encola
 *    directo.
 * 2. Si está online, intenta el fetch real contra la ruta del form.
 *    - Si responde OK: éxito, no hay nada más que hacer (el caller decide
 *      qué mostrar/resetear).
 *    - Si el fetch FALLA (TypeError de red — server inalcanzable, sin
 *      conexión real aunque navigator.onLine diga que sí): se encola.
 *    - Si el fetch responde pero con un status de error (4xx/5xx real,
 *      ej. una validación que no depende de conectividad): NO se encola —
 *      reintentar exactamente lo mismo no lo va a arreglar — se devuelve
 *      tal cual para que el caller muestre el error.
 *
 * El mismo uuid_dispositivo generado acá viaja tanto en el intento
 * inmediato como en lo que eventualmente quede guardado en la cola: es la
 * clave de la idempotencia (ver el comentario grande al principio del
 * archivo) — nunca se regenera en un reintento posterior.
 *
 * @param {HTMLFormElement} form
 * @param {'venta'|'pago'} tipo
 * @returns {Promise<{estado: 'enviada'|'encolada'|'error', response?: Response}>}
 */
export async function enviarOEncolar(form, tipo) {
    let uuidInput = form.querySelector('input[name="uuid_dispositivo"]');
    if (!uuidInput) {
        uuidInput = document.createElement('input');
        uuidInput.type = 'hidden';
        uuidInput.name = 'uuid_dispositivo';
        form.appendChild(uuidInput);
    }
    if (!uuidInput.value) {
        uuidInput.value = crypto.randomUUID();
    }

    const entradas = serializarForm(form);
    const nombreStore = tipo === 'pago' ? STORE_PAGOS : STORE_VENTAS;

    async function encolar() {
        await guardar(nombreStore, {
            uuid_dispositivo: uuidInput.value,
            tipo,
            action: form.action,
            entradas,
            creadoEn: new Date().toISOString(),
        });
        notificarCambioCola();
        return { estado: 'encolada' };
    }

    if (!navigator.onLine) {
        return encolar();
    }

    try {
        // Accept: application/json para que un fallo de validación (ej. una
        // venta online con stock insuficiente) vuelva como JSON parseable
        // ({ errors: {...} }) en vez del redirect-con-flash pensado para un
        // <form> nativo — el caller (ver ventas/create.blade.php y
        // clientes/show.blade.php) lo necesita para mostrar el mensaje. No
        // afecta el camino de éxito: ahí el servidor sigue respondiendo un
        // redirect (302) y fetch lo sigue solo, sin que este header cambie nada.
        const response = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body: armarFormData(entradas),
        });

        if (response.ok) {
            return { estado: 'enviada', response };
        }

        return { estado: 'error', response };
    } catch (error) {
        // TypeError de fetch: red caída de verdad (a pesar de lo que diga
        // navigator.onLine — ej. wifi conectado pero sin salida a internet).
        return encolar();
    }
}

/**
 * Recorre la cola y reintenta cada item pendiente contra su ruta real. Se
 * dispara al detectar el evento 'online' del browser y también una vez al
 * cargar la página si ya está online (ver el listener al final de este
 * archivo) — no hace falta que el usuario haga nada.
 *
 * Si un item falla por RED (fetch lanza), se corta el recorrido entero: si
 * la red se cayó a mitad de sync, el resto de los items casi seguro también
 * va a fallar, y no tiene sentido golpearla de nuevo por cada uno. Un
 * próximo evento 'online' vuelve a intentar desde el principio. Si un item
 * responde pero con error de servidor (no debería pasar en el uso normal:
 * el stock offline nunca bloquea, ver VentaController::store), queda en la
 * cola pero se sigue con el resto — no es un problema de red, reintentarlo
 * en loop no lo arregla solo.
 */
export async function sincronizarPendientes() {
    if (!navigator.onLine) {
        return;
    }

    const [ventas, pagos] = await Promise.all([listar(STORE_VENTAS), listar(STORE_PAGOS)]);
    const pendientes = [...ventas, ...pagos];

    for (const item of pendientes) {
        const nombreStore = item.tipo === 'pago' ? STORE_PAGOS : STORE_VENTAS;

        try {
            // eslint-disable-next-line no-await-in-loop
            const response = await fetch(item.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                body: armarFormData(item.entradas),
            });

            if (response.ok) {
                // eslint-disable-next-line no-await-in-loop
                await eliminar(nombreStore, item.uuid_dispositivo);
                notificarCambioCola();
            }
        } catch (error) {
            break;
        }
    }
}

// Sync automática: al volver la señal (evento 'online' del browser) y una
// vez al cargar la página si ya está online — sin que el cajero tenga que
// hacer nada. Ver el comentario grande al principio del archivo sobre por
// qué no hace falta Background Sync API para el caso real de este negocio.
window.addEventListener('online', () => {
    sincronizarPendientes();
});

if (navigator.onLine) {
    sincronizarPendientes();
}

/**
 * Catálogo de productos cacheado en IndexedDB (documento de alcance,
 * arquitectura offline — "productos necesarios disponibles offline"): sin
 * esto, buscar un producto en /ventas/create con la señal cortada fallaría
 * siempre, porque la búsqueda en vivo (ver ventas/create.blade.php) le pega
 * a productos.buscar por red. Se sincroniza (reemplazo completo, no merge
 * incremental — un catálogo de este tamaño no lo necesita) cada vez que la
 * página carga con conexión, así que el peor caso es buscar contra una foto
 * de hasta la última vez que hubo señal, nunca contra nada.
 *
 * @param {string} url ruta de productos.catalogo
 */
export async function sincronizarCatalogoProductos(url) {
    if (!navigator.onLine) {
        return;
    }

    try {
        const respuesta = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!respuesta.ok) {
            return;
        }

        const productos = await respuesta.json();
        await reemplazarTodos(STORE_PRODUCTOS, productos);
    } catch (error) {
        // Sin conexión real a pesar de navigator.onLine, o servidor caído:
        // el cache local queda tal cual estaba (una foto vieja es mejor que
        // ninguna) — no es un error que el cajero necesite ver.
    }
}

/**
 * Búsqueda tolerante equivalente a Producto::scopeSearch (ver ese método en
 * PHP): todas las palabras del término deben aparecer como substring de
 * `nombre`, o el término completo coincide con `codigo_barras`. Se duplica
 * la lógica acá en vez de compartirla porque un lado corre como SQL (MySQL
 * LIKE) y el otro en JS puro contra un array ya en memoria — no hay forma
 * de compartir una sola implementación entre PHP y el navegador.
 *
 * @param {string} termino
 * @returns {Promise<Array<object>>}
 */
export async function buscarEnCatalogoLocal(termino) {
    const term = termino.trim();
    if (term === '') {
        return [];
    }

    const productos = await listar(STORE_PRODUCTOS);
    const normalizar = (texto) => texto.toLowerCase();
    const termNormalizado = normalizar(term);
    const palabras = term.split(/\s+/).map(normalizar).filter((palabra) => palabra.length > 1);

    return productos.filter((producto) => {
        if (producto.codigo_barras && normalizar(producto.codigo_barras).includes(termNormalizado)) {
            return true;
        }

        const nombre = normalizar(producto.nombre);

        return palabras.length > 0
            ? palabras.every((palabra) => nombre.includes(palabra))
            : nombre.includes(termNormalizado);
    }).slice(0, 20);
}

/**
 * Match exacto de código de barras contra el cache local — usado por el
 * flujo de Enter/lector de código cuando la búsqueda por red falló (ver
 * ventas/create.blade.php).
 *
 * @param {string} codigo
 * @returns {Promise<?object>}
 */
export async function buscarPorCodigoExactoLocal(codigo) {
    const productos = await listar(STORE_PRODUCTOS);
    return productos.find((producto) => producto.codigo_barras === codigo) ?? null;
}

window.offlineSync = {
    enviarOEncolar,
    contarPendientes,
    sincronizarPendientes,
    sincronizarCatalogoProductos,
    buscarEnCatalogoLocal,
    buscarPorCodigoExactoLocal,
};
