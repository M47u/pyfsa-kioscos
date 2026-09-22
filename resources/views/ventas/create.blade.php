@extends('layouts.app')

@section('title', 'Nueva venta')

@section('body-class', 'p-6')
@section('container-class', 'max-w-2xl mx-auto')

@section('content')
    <h1 class="text-lg font-medium mb-1">Nueva venta</h1>
    <p class="text-xs opacity-60 mb-6">
        F2 buscar &middot; F4 cobrar &middot; Esc cancelar &middot; + / − cantidad &middot; Supr quitar
        <span class="hidden sm:inline">(con un artículo del carrito seleccionado, hacé click en la fila)</span>
    </p>

    <x-validation-errors />

    {{-- Offline (ver CLAUDE.md, arquitectura offline y resources/js/offline.js):
         mensaje de resultado del submit por fetch — éxito, guardada
         localmente (sin conexión) o error de validación. Arranca hidden, lo
         maneja el <script> de abajo; @csrf/@validation-errors de arriba
         siguen siendo el fallback si el navegador no corre JS. --}}
    <div id="venta-feedback" hidden class="mb-4 rounded-sm border px-4 py-3 text-sm"></div>

    <form method="POST" action="{{ route('ventas.store') }}" id="venta-form" class="space-y-6">
        @csrf

        {{-- Orden del flujo real de mostrador: primero se cargan los
             artículos (escaneo/búsqueda + cantidad), recién al final se
             elige cómo se cobra — no al revés. --}}
        <div>
            <label for="producto-search" class="block text-sm font-medium mb-1">Buscar o escanear artículo (F2)</label>
            {{--
                Input de texto, no <select>: un lector de código de barras
                USB/Bluetooth "tipea" el código acá adentro y manda un Enter
                solo, sin integración especial. Un <select> con el catálogo
                entero tampoco es usable pasados unos pocos cientos de
                productos. Búsqueda tolerante a texto parcial/orden de
                palabras (ver Producto::scopeSearch) y con fallback al
                catálogo cacheado en IndexedDB cuando no hay conexión (ver
                resources/js/offline.js).
            --}}
            <div class="relative flex gap-2">
                <div class="flex-1 relative">
                    <input
                        type="text"
                        id="producto-search"
                        autocomplete="off"
                        autofocus
                        placeholder="Nombre o código de barras..."
                        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                    >
                    <ul
                        id="producto-resultados"
                        hidden
                        class="absolute z-10 mt-1 w-full max-h-56 overflow-y-auto rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-sm shadow-lg"
                    ></ul>
                </div>
                <input
                    type="number"
                    id="cantidad-input"
                    min="1"
                    value="1"
                    class="w-20 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-2 py-2 text-sm"
                >
                {{-- Escaneo por cámara (BarcodeDetector nativo del
                     navegador — sin librería nueva de Composer/npm, ver
                     comentario en el <script> de abajo). Degradación
                     explícita: si el navegador no lo soporta (Firefox,
                     Safari de escritorio), el modal avisa en vez de fallar
                     en silencio. --}}
                <button
                    type="button"
                    id="escanear-camara-btn"
                    title="Escanear con la cámara"
                    aria-label="Escanear con la cámara"
                    class="shrink-0 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-3 py-2 text-sm"
                >
                    📷
                </button>
            </div>
            <p id="producto-sin-resultados" hidden class="mt-1 text-xs opacity-70">
                No se encontró ningún artículo con ese nombre o código.
                @if (auth()->user()->esDueno())
                    <a href="#" id="crear-articulo-link" class="underline" target="_blank" rel="noopener">Crear artículo nuevo</a>
                @endif
            </p>
        </div>

        {{-- Productos frecuentes (POS/UX, gap encontrado por el usuario):
             top-8 por cantidad vendida en los últimos 30 días (ver
             VentaController::productosFrecuentes()) como botones de acceso
             directo — el kiosquero no debería tener que buscar/escanear lo
             que vende todo el día. Server-rendered: sigue disponible
             offline vía el cacheo del Service Worker de /ventas/create
             (una foto de la última vez que se abrió la página con
             conexión, no en vivo). --}}
        @if ($productosFrecuentes->isNotEmpty())
            <div>
                <p class="text-xs font-medium opacity-70 mb-2">Frecuentes</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($productosFrecuentes as $producto)
                        <button
                            type="button"
                            class="frecuente-btn rounded-full border border-[#19140035] dark:border-[#3E3E3A] px-3 py-1.5 text-sm"
                            data-id="{{ $producto->id }}"
                            data-nombre="{{ $producto->nombre }}"
                            data-precio="{{ $producto->precio_venta }}"
                        >
                            {{ $producto->nombre }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div>
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <th class="py-2 pr-4">Artículo</th>
                        <th class="py-2 pr-4">Cantidad</th>
                        <th class="py-2 pr-4">Precio unit.</th>
                        <th class="py-2 pr-4">Subtotal</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody id="carrito-body"></tbody>
            </table>
            <div class="flex items-center justify-between py-2">
                <p id="carrito-vacio-msg" class="text-sm opacity-70">
                    El carrito está vacío.
                </p>
                <button type="button" id="vaciar-carrito-btn" hidden class="text-sm underline opacity-70">
                    Vaciar carrito
                </button>
            </div>
            <div id="items-inputs"></div>
        </div>

        <div class="text-right text-base font-medium">
            Total: $<span id="total-venta">0.00</span>
        </div>

        {{-- Recién acá, con los artículos ya cargados, se elige cómo se
             cobra. Botones grandes en vez de <select>: menos clicks/toques
             y coincide con el flujo TOTAL -> EFECTIVO/DÉBITO/QR/CUENTA del
             documento de alcance. --}}
        <div id="cobro-panel" class="border-t border-[#19140035] dark:border-[#3E3E3A] pt-4 space-y-4">
            <p class="text-sm font-medium">Cobrar (F4)</p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button type="button" data-medio="efectivo" class="medio-pago-btn rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-3 text-sm font-medium">
                    💵<br>Efectivo
                </button>
                <button type="button" data-medio="debito" class="medio-pago-btn rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-3 text-sm font-medium">
                    💳<br>Débito
                </button>
                <button type="button" data-medio="qr" class="medio-pago-btn rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-3 text-sm font-medium">
                    📱<br>QR
                </button>
                <button type="button" data-medio="fiado" class="medio-pago-btn rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-3 text-sm font-medium">
                    📒<br>Cuenta
                </button>
            </div>
            <input type="hidden" id="medio_pago" name="medio_pago">

            {{-- Solo con "Efectivo": calculadora de vuelto, bloquea
                 confirmar si el monto ingresado no alcanza. --}}
            <div id="monto-efectivo-wrapper" hidden>
                <label for="monto-efectivo-input" class="block text-sm font-medium mb-1">Monto abonado en efectivo</label>
                <input
                    type="number"
                    id="monto-efectivo-input"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                >
                <p class="mt-1 text-sm">
                    Vuelto: $<span id="vuelto-venta">0.00</span>
                </p>
                <p id="monto-insuficiente-msg" hidden class="mt-1 text-xs text-[#F53003] dark:text-[#FF4433]">
                    El monto ingresado no cubre el total — no se puede confirmar el cobro todavía.
                </p>
            </div>

            {{-- Solo con "Cuenta": cliente existente o alta rápida sin salir
                 de esta pantalla. --}}
            <div id="cliente-wrapper" hidden>
                <label for="cliente_id" class="block text-sm font-medium mb-1">Cliente</label>
                <select
                    id="cliente_id"
                    name="cliente_id"
                    class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                >
                    <option value="">-- Seleccionar cliente --</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                    @endforeach
                </select>

                <button type="button" id="mostrar-nuevo-cliente-btn" class="mt-1 text-xs underline">
                    + Crear cliente nuevo
                </button>

                <div id="nuevo-cliente-form" hidden class="mt-2 space-y-2 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-3">
                    <input
                        type="text"
                        id="nuevo-cliente-nombre"
                        placeholder="Nombre"
                        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                    >
                    <input
                        type="text"
                        id="nuevo-cliente-telefono"
                        placeholder="Teléfono (opcional)"
                        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                    >
                    <div class="flex gap-2">
                        <button type="button" id="crear-cliente-btn" class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-3 py-1.5 text-sm font-medium">
                            Crear y seleccionar
                        </button>
                        <button type="button" id="cancelar-nuevo-cliente-btn" class="text-sm underline">
                            Cancelar
                        </button>
                    </div>
                    <p id="nuevo-cliente-error" hidden class="text-xs text-[#F53003] dark:text-[#FF4433]"></p>
                </div>
            </div>

            <button
                type="submit"
                id="registrar-venta-btn"
                disabled
                class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium disabled:opacity-50"
            >
                Confirmar cobro
            </button>
        </div>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('ventas.index') }}" class="text-sm underline">Volver a ventas</a>
    </div>

    {{-- Mismo componente que la confirmación de pago en clientes/show.blade.php
         — ver el <script> de abajo para cuándo se abre. --}}
    <x-confirm-dialog
        id="confirmar-salir-dialog"
        titulo="¿Salir sin registrar la venta?"
        confirmar-label="Salir de todos modos"
        cancelar-label="Seguir cargando"
    >
        Tenés <strong><span id="cantidad-items-carrito">0</span> artículo(s)</strong> cargados en el carrito.
        Si salís ahora, <strong>esta venta no se va a registrar</strong>.
    </x-confirm-dialog>

    {{-- Modal de escaneo por cámara. <dialog> nativo (no x-confirm-dialog:
         ese componente es para confirmar/cancelar una acción, esto necesita
         un <video> en vivo y su propio ciclo de vida de MediaStream). --}}
    <dialog id="camara-dialog" class="rounded-sm p-0 backdrop:bg-black/60">
        <div class="p-4 space-y-3 w-full sm:w-96">
            <p class="text-sm font-medium">Escaneá un código de barras</p>
            <video id="camara-video" class="w-full rounded-sm bg-black aspect-video" playsinline muted></video>
            <p id="camara-error" hidden class="text-xs text-[#F53003] dark:text-[#FF4433]"></p>
            <button type="button" id="cerrar-camara-btn" class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-2 text-sm">
                Cerrar (Esc)
            </button>
        </div>
    </dialog>

    <script>
        (function () {
            const ventaForm = document.getElementById('venta-form');
            const ventaFeedback = document.getElementById('venta-feedback');
            const productoSearch = document.getElementById('producto-search');
            const productoResultados = document.getElementById('producto-resultados');
            const productoSinResultados = document.getElementById('producto-sin-resultados');
            const crearArticuloLink = document.getElementById('crear-articulo-link');
            const cantidadInput = document.getElementById('cantidad-input');
            const carritoBody = document.getElementById('carrito-body');
            const carritoVacioMsg = document.getElementById('carrito-vacio-msg');
            const vaciarCarritoBtn = document.getElementById('vaciar-carrito-btn');
            const itemsInputs = document.getElementById('items-inputs');
            const totalSpan = document.getElementById('total-venta');
            const registrarBtn = document.getElementById('registrar-venta-btn');
            const medioPagoInput = document.getElementById('medio_pago');
            const medioPagoBotones = document.querySelectorAll('.medio-pago-btn');
            const clienteWrapper = document.getElementById('cliente-wrapper');
            const clienteSelect = document.getElementById('cliente_id');
            const montoEfectivoWrapper = document.getElementById('monto-efectivo-wrapper');
            const montoEfectivoInput = document.getElementById('monto-efectivo-input');
            const vueltoSpan = document.getElementById('vuelto-venta');
            const montoInsuficienteMsg = document.getElementById('monto-insuficiente-msg');
            const mostrarNuevoClienteBtn = document.getElementById('mostrar-nuevo-cliente-btn');
            const nuevoClienteForm = document.getElementById('nuevo-cliente-form');
            const nuevoClienteNombre = document.getElementById('nuevo-cliente-nombre');
            const nuevoClienteTelefono = document.getElementById('nuevo-cliente-telefono');
            const crearClienteBtn = document.getElementById('crear-cliente-btn');
            const cancelarNuevoClienteBtn = document.getElementById('cancelar-nuevo-cliente-btn');
            const nuevoClienteError = document.getElementById('nuevo-cliente-error');
            const escanearCamaraBtn = document.getElementById('escanear-camara-btn');
            const camaraDialog = document.getElementById('camara-dialog');
            const camaraVideo = document.getElementById('camara-video');
            const camaraError = document.getElementById('camara-error');
            const cerrarCamaraBtn = document.getElementById('cerrar-camara-btn');

            const RUTA_BUSCAR = '{{ route('productos.buscar') }}';
            const RUTA_CATALOGO = '{{ route('productos.catalogo') }}';
            const RUTA_CLIENTES_STORE = '{{ route('clientes.store') }}';
            const RUTA_PRODUCTOS_CREATE = '{{ route('productos.create') }}';

            let carrito = [];
            let totalActual = 0;
            let filaSeleccionada = null;
            let medioPagoActual = null;

            function debounce(fn, ms) {
                let timeoutId;
                return (...args) => {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => fn(...args), ms);
                };
            }

            // Offline (ver CLAUDE.md, arquitectura offline): sincroniza el
            // catálogo local apenas carga la página, si hay conexión —
            // es el fallback que usa buscarProductosConFallback() de abajo
            // cuando la señal se corta a mitad de una venta.
            if (window.offlineSync) {
                window.offlineSync.sincronizarCatalogoProductos(RUTA_CATALOGO);
            }

            async function buscarProductosRemoto(term) {
                const respuesta = await fetch(`${RUTA_BUSCAR}?buscar=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!respuesta.ok) {
                    throw new Error('busqueda_fallo');
                }

                return respuesta.json();
            }

            async function buscarProductosConFallback(term) {
                try {
                    return await buscarProductosRemoto(term);
                } catch (error) {
                    return window.offlineSync ? window.offlineSync.buscarEnCatalogoLocal(term) : [];
                }
            }

            async function buscarProductoPorCodigoExacto(term) {
                try {
                    const productos = await buscarProductosRemoto(term);
                    return productos.find((producto) => producto.codigo_barras === term) ??
                        (productos.length === 1 ? productos[0] : null);
                } catch (error) {
                    if (!window.offlineSync) {
                        return null;
                    }

                    const exacto = await window.offlineSync.buscarPorCodigoExactoLocal(term);
                    if (exacto) {
                        return exacto;
                    }

                    const locales = await window.offlineSync.buscarEnCatalogoLocal(term);
                    return locales.length === 1 ? locales[0] : null;
                }
            }

            function mostrarSinResultados(term) {
                productoSinResultados.hidden = false;
                if (crearArticuloLink) {
                    crearArticuloLink.href = `${RUTA_PRODUCTOS_CREATE}?codigo_barras=${encodeURIComponent(term)}`;
                }
            }

            function agregarProducto(producto) {
                const cantidad = parseInt(cantidadInput.value, 10);
                if (!producto || !cantidad || cantidad < 1) {
                    return;
                }

                // Código de barras / frecuente ya en el carrito: suma la
                // cantidad en vez de duplicar la fila (documento de
                // alcance, flujo de código de barras: "incrementar cantidad
                // si ya existe").
                const indiceExistente = carrito.findIndex((item) => item.productoId === producto.id);

                if (indiceExistente !== -1) {
                    carrito[indiceExistente].cantidad += cantidad;
                    filaSeleccionada = indiceExistente;
                } else {
                    carrito.push({
                        productoId: producto.id,
                        nombre: producto.nombre,
                        precio: producto.precio_venta,
                        cantidad,
                    });
                    filaSeleccionada = carrito.length - 1;
                }

                productoSearch.value = '';
                cantidadInput.value = 1;
                ocultarResultados();
                render();
                productoSearch.focus();
            }

            function ocultarResultados() {
                productoResultados.hidden = true;
                productoResultados.innerHTML = '';
                productoSinResultados.hidden = true;
            }

            // Arma los <li> con DOM + textContent en vez de innerHTML con el
            // nombre/código interpolados a mano: son datos que vienen de la
            // base (nombre de producto), no confiables como HTML — con
            // innerHTML un nombre de producto con '<' adentro terminaría
            // interpretado como markup en vez de texto plano.
            function mostrarResultados(productos) {
                productoSinResultados.hidden = productos.length !== 0;
                productoResultados.hidden = productos.length === 0;
                productoResultados.innerHTML = '';

                if (productos.length === 0) {
                    return;
                }

                productos.forEach((producto) => {
                    const li = document.createElement('li');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'block w-full text-left px-3 py-2 hover:bg-[#f5f5f4] dark:hover:bg-[#1f1f1d]';
                    btn.textContent = `${producto.nombre} (${producto.codigo_barras ?? 's/código'}) — $${producto.precio_venta.toFixed(2)}`;
                    btn.addEventListener('click', () => agregarProducto(producto));
                    li.appendChild(btn);
                    productoResultados.appendChild(li);
                });
            }

            const buscarProductos = debounce(async (term) => {
                if (term.length < 2) {
                    ocultarResultados();
                    return;
                }

                mostrarResultados(await buscarProductosConFallback(term));
            }, 200);

            productoSearch.addEventListener('input', () => buscarProductos(productoSearch.value.trim()));

            // El lector de código de barras "tipea" el código y manda un
            // Enter solo, casi sin pausa. Enter dispara su propia búsqueda
            // inmediata (no reusa el debounce de 200ms del 'input', que a
            // esa velocidad puede no haber resuelto todavía) y agrega el
            // producto cuyo código coincide EXACTO con lo tipeado; si no
            // hay coincidencia exacta pero la búsqueda deja un único
            // resultado (alguien tipeando un nombre a mano), se agrega ese.
            productoSearch.addEventListener('keydown', async (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault(); // no confundir con el submit del form de la venta

                const term = productoSearch.value.trim();
                if (term === '') {
                    return;
                }

                const match = await buscarProductoPorCodigoExacto(term);
                ocultarResultados();

                if (match) {
                    agregarProducto(match);
                } else {
                    mostrarSinResultados(term);
                }
            });

            // Productos frecuentes: mismo agregarProducto() que la búsqueda
            // — no hay campo controla_stock acá porque el backend
            // (VentaRequest/VentaController) es quien decide bloquear o no
            // por stock, este botón solo carga el carrito.
            document.querySelectorAll('.frecuente-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    agregarProducto({
                        id: Number(btn.dataset.id),
                        nombre: btn.dataset.nombre,
                        precio_venta: parseFloat(btn.dataset.precio),
                    });
                });
            });

            // ---- Cobro: medios de pago (botones, no <select>) ----
            const CLASE_MEDIO_BASE = 'medio-pago-btn rounded-sm border px-3 py-3 text-sm font-medium';
            const CLASE_MEDIO_INACTIVO = 'border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC]';
            const CLASE_MEDIO_ACTIVO = 'border-transparent bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A]';

            function actualizarBotonesMedioPago() {
                medioPagoBotones.forEach((btn) => {
                    const activo = btn.dataset.medio === medioPagoActual;
                    btn.className = `${CLASE_MEDIO_BASE} ${activo ? CLASE_MEDIO_ACTIVO : CLASE_MEDIO_INACTIVO}`;
                });
            }

            function seleccionarMedioPago(medio) {
                medioPagoActual = medio;
                medioPagoInput.value = medio;
                actualizarBotonesMedioPago();

                clienteWrapper.hidden = medio !== 'fiado';
                montoEfectivoWrapper.hidden = medio !== 'efectivo';

                if (!montoEfectivoWrapper.hidden) {
                    actualizarVuelto();
                }

                actualizarEstadoBoton();
            }

            medioPagoBotones.forEach((btn) => {
                btn.addEventListener('click', () => seleccionarMedioPago(btn.dataset.medio));
            });

            // Vuelto = lo que abona el cliente en efectivo - el total de la
            // venta. Se recalcula acá (input del monto) Y desde render() de
            // abajo (el total cambia cada vez que se agrega/quita un
            // artículo). Un monto insuficiente BLOQUEA confirmar (ver
            // actualizarEstadoBoton) — a diferencia de dejarlo vacío, que no
            // bloquea (el kiosquero puede cobrar "justo" sin escribir nada).
            function actualizarVuelto() {
                const montoEfectivo = parseFloat(montoEfectivoInput.value);
                const vuelto = (isNaN(montoEfectivo) ? 0 : montoEfectivo) - totalActual;
                const insuficiente = montoEfectivoInput.value !== '' && vuelto < 0;

                vueltoSpan.textContent = vuelto.toFixed(2);
                vueltoSpan.classList.toggle('text-[#F53003]', insuficiente);
                vueltoSpan.classList.toggle('dark:text-[#FF4433]', insuficiente);
                montoInsuficienteMsg.hidden = !insuficiente;

                actualizarEstadoBoton();
            }

            montoEfectivoInput.addEventListener('input', actualizarVuelto);
            // Enter en este campo debe poder confirmar el cobro (submit
            // "inteligente" — ver el resto del flujo de teclado), no
            // insertar un salto de línea ni nada raro: type="number" ya lo
            // evita, no hace falta un handler propio.

            clienteSelect.addEventListener('change', actualizarEstadoBoton);

            function actualizarEstadoBoton() {
                const montoEfectivo = parseFloat(montoEfectivoInput.value);
                const efectivoInsuficiente = medioPagoActual === 'efectivo'
                    && montoEfectivoInput.value !== ''
                    && !isNaN(montoEfectivo)
                    && montoEfectivo < totalActual;
                const faltaCliente = medioPagoActual === 'fiado' && !clienteSelect.value;

                registrarBtn.disabled = carrito.length === 0 || !medioPagoActual || efectivoInsuficiente || faltaCliente;
            }

            // ---- Cliente rápido (alta sin salir de la pantalla de venta) ----
            mostrarNuevoClienteBtn.addEventListener('click', () => {
                nuevoClienteForm.hidden = false;
                nuevoClienteNombre.focus();
            });

            cancelarNuevoClienteBtn.addEventListener('click', () => {
                nuevoClienteForm.hidden = true;
                nuevoClienteNombre.value = '';
                nuevoClienteTelefono.value = '';
                nuevoClienteError.hidden = true;
            });

            async function crearClienteRapido() {
                const nombre = nuevoClienteNombre.value.trim();

                if (nombre === '') {
                    nuevoClienteError.hidden = false;
                    nuevoClienteError.textContent = 'Ingresá un nombre.';
                    return;
                }

                crearClienteBtn.disabled = true;

                try {
                    const respuesta = await fetch(RUTA_CLIENTES_STORE, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': ventaForm.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({ nombre, telefono: nuevoClienteTelefono.value.trim() || null }),
                    });

                    if (!respuesta.ok) {
                        const cuerpo = await respuesta.json().catch(() => null);
                        nuevoClienteError.hidden = false;
                        nuevoClienteError.textContent = cuerpo && cuerpo.errors
                            ? Object.values(cuerpo.errors).flat().join(' ')
                            : 'No se pudo crear el cliente (¿estás sin conexión?).';
                        return;
                    }

                    const cliente = await respuesta.json();
                    const option = document.createElement('option');
                    option.value = cliente.id;
                    option.textContent = cliente.nombre;
                    clienteSelect.appendChild(option);
                    clienteSelect.value = String(cliente.id);

                    nuevoClienteForm.hidden = true;
                    nuevoClienteNombre.value = '';
                    nuevoClienteTelefono.value = '';
                    nuevoClienteError.hidden = true;
                    actualizarEstadoBoton();
                } catch (error) {
                    nuevoClienteError.hidden = false;
                    nuevoClienteError.textContent = 'No se pudo crear el cliente (¿estás sin conexión?).';
                } finally {
                    crearClienteBtn.disabled = false;
                }
            }

            crearClienteBtn.addEventListener('click', crearClienteRapido);

            [nuevoClienteNombre, nuevoClienteTelefono].forEach((input) => {
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault(); // no confundir con el submit de la venta
                        crearClienteRapido();
                    }
                });
            });

            // ---- Carrito: render, selección de fila y cantidades ----
            function bloquearEnterYCommitear(input) {
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault(); // no confundir con el submit de la venta
                        input.blur(); // dispara 'change' para confirmar el valor
                    }
                });
            }

            bloquearEnterYCommitear(cantidadInput);

            function enfocarFilaSeleccionada() {
                if (filaSeleccionada === null) {
                    return;
                }

                const fila = carritoBody.querySelector(`[data-index="${filaSeleccionada}"]`);
                if (fila) {
                    fila.focus();
                }
            }

            function render() {
                carritoBody.innerHTML = '';
                itemsInputs.innerHTML = '';
                let total = 0;

                if (filaSeleccionada !== null && (filaSeleccionada < 0 || filaSeleccionada >= carrito.length)) {
                    filaSeleccionada = null;
                }

                carrito.forEach((item, index) => {
                    const subtotal = item.precio * item.cantidad;
                    total += subtotal;

                    const seleccionada = index === filaSeleccionada;

                    // textContent para item.nombre (viene de la base, no es
                    // HTML de confianza) en vez de interpolarlo en innerHTML.
                    const row = document.createElement('tr');
                    row.tabIndex = 0;
                    row.dataset.index = String(index);
                    row.className = 'cursor-pointer border-b border-[#19140035] dark:border-[#3E3E3A]'
                        + (seleccionada ? ' bg-[#f5f5f4] dark:bg-[#1f1f1d] outline outline-2 outline-[#1b1b18] dark:outline-[#eeeeec] -outline-offset-2' : '');

                    const tdNombre = document.createElement('td');
                    tdNombre.className = 'py-2 pr-4';
                    tdNombre.textContent = item.nombre;
                    row.appendChild(tdNombre);

                    row.insertAdjacentHTML('beforeend', `
                        <td class="py-2 pr-4">
                            <input
                                type="number"
                                min="1"
                                value="${item.cantidad}"
                                data-index="${index}"
                                class="cantidad-item w-16 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-2 py-1 text-sm"
                            >
                        </td>
                        <td class="py-2 pr-4">${item.precio.toFixed(2)}</td>
                        <td class="py-2 pr-4">${subtotal.toFixed(2)}</td>
                        <td class="py-2"><button type="button" data-index="${index}" class="quitar-item underline text-sm">Quitar</button></td>
                    `);
                    carritoBody.appendChild(row);

                    // Seleccionar una fila (click o foco por teclado) es lo
                    // que habilita los atajos +/-/Supr sobre ESA fila — ver
                    // el listener de document abajo.
                    row.addEventListener('click', (event) => {
                        if (event.target.closest('button') || event.target.closest('input')) {
                            return;
                        }
                        filaSeleccionada = index;
                        render();
                    });
                    row.addEventListener('focus', () => {
                        filaSeleccionada = index;
                    });

                    itemsInputs.insertAdjacentHTML('beforeend', `
                        <input type="hidden" name="items[${index}][producto_id]" value="${item.productoId}">
                        <input type="hidden" name="items[${index}][cantidad]" value="${item.cantidad}">
                    `);
                });

                carritoVacioMsg.hidden = carrito.length > 0;
                vaciarCarritoBtn.hidden = carrito.length === 0;
                totalActual = total;
                totalSpan.textContent = total.toFixed(2);

                if (!montoEfectivoWrapper.hidden) {
                    actualizarVuelto();
                }

                actualizarEstadoBoton();

                // 'change' (blur/Enter), no 'input': cada cambio dispara
                // render(), que reconstruye el <tbody> entero — si
                // reaccionara a cada tecla, el input perdería el foco a
                // mitad de tipear un número de más de un dígito.
                carritoBody.querySelectorAll('.cantidad-item').forEach((input) => {
                    bloquearEnterYCommitear(input);
                    input.addEventListener('change', () => {
                        const index = Number(input.dataset.index);
                        const cantidad = parseInt(input.value, 10);

                        if (!cantidad || cantidad < 1) {
                            input.value = carrito[index].cantidad; // revierte, no deja cantidad inválida
                            return;
                        }

                        carrito[index].cantidad = cantidad;
                        filaSeleccionada = index;
                        render();
                    });
                });

                carritoBody.querySelectorAll('.quitar-item').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const index = Number(btn.dataset.index);
                        carrito.splice(index, 1);

                        if (filaSeleccionada !== null) {
                            if (filaSeleccionada === index) {
                                filaSeleccionada = null;
                            } else if (filaSeleccionada > index) {
                                filaSeleccionada -= 1;
                            }
                        }

                        render();
                    });
                });
            }

            vaciarCarritoBtn.addEventListener('click', () => {
                carrito = [];
                filaSeleccionada = null;
                render();
            });

            render();
            seleccionarMedioPago('efectivo'); // default: minimiza pasos para el caso más común

            // ---- Atajos de teclado (POS/UX) ----
            function esElementoEditable(el) {
                if (!el) {
                    return false;
                }
                const tag = el.tagName;
                return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
            }

            document.addEventListener('keydown', (event) => {
                if (event.key === 'F2') {
                    event.preventDefault();
                    productoSearch.focus();
                    productoSearch.select();
                    return;
                }

                if (event.key === 'F4') {
                    event.preventDefault();
                    if (!registrarBtn.disabled) {
                        ventaForm.requestSubmit();
                    } else if (carrito.length > 0) {
                        document.getElementById('cobro-panel').scrollIntoView({ behavior: 'smooth', block: 'center' });
                        medioPagoBotones[0].focus();
                    }
                    return;
                }

                if (event.key === 'Escape') {
                    if (camaraDialog.open) {
                        cerrarCamara();
                        return;
                    }
                    if (!productoResultados.hidden) {
                        ocultarResultados();
                        return;
                    }
                    if (filaSeleccionada !== null) {
                        filaSeleccionada = null;
                        render();
                    }
                    return;
                }

                // +/-/Supr: solo cuando el foco NO está en un campo de
                // edición (input/textarea/select) — así el lector de
                // código, el monto en efectivo o el alta rápida de cliente
                // siguen aceptando esos caracteres con normalidad. Estos
                // atajos son para cuando el cajero seleccionó una fila del
                // carrito con el mouse/touch (o Tab) y quiere ajustarla sin
                // volver al mouse.
                if (esElementoEditable(document.activeElement) || filaSeleccionada === null) {
                    return;
                }

                if (event.key === '+') {
                    event.preventDefault();
                    carrito[filaSeleccionada].cantidad += 1;
                    render();
                    enfocarFilaSeleccionada();
                } else if (event.key === '-') {
                    event.preventDefault();
                    carrito[filaSeleccionada].cantidad = Math.max(1, carrito[filaSeleccionada].cantidad - 1);
                    render();
                    enfocarFilaSeleccionada();
                } else if (event.key === 'Delete') {
                    event.preventDefault();
                    carrito.splice(filaSeleccionada, 1);
                    filaSeleccionada = null;
                    render();
                }
            });

            // ---- Escaneo por cámara ----
            // BarcodeDetector es una API nativa del navegador (Chrome/Edge/
            // Android WebView) — sin librería nueva de Composer/npm. Se
            // degrada explícitamente en vez de fallar en silencio: Firefox
            // y Safari de escritorio no la soportan hoy, y en ese caso el
            // modal avisa que hay que usar el lector USB o la búsqueda
            // manual (documento de alcance: "no complicar la UX").
            let streamCamara = null;
            let detectorCamara = null;
            let frameCamara = null;

            async function abrirCamara() {
                camaraError.hidden = true;

                if (!('BarcodeDetector' in window)) {
                    camaraError.hidden = false;
                    camaraError.textContent = 'Este navegador no soporta escaneo por cámara. Usá el lector USB o buscá manualmente.';
                    camaraDialog.showModal();
                    return;
                }

                try {
                    streamCamara = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                } catch (error) {
                    camaraError.hidden = false;
                    camaraError.textContent = 'No se pudo acceder a la cámara. Revisá los permisos del navegador.';
                    camaraDialog.showModal();
                    return;
                }

                camaraVideo.srcObject = streamCamara;
                camaraDialog.showModal();
                await camaraVideo.play();

                detectorCamara = new BarcodeDetector();
                detectarLoop();
            }

            async function detectarLoop() {
                if (!streamCamara) {
                    return;
                }

                try {
                    const codigos = await detectorCamara.detect(camaraVideo);

                    if (codigos.length > 0) {
                        const valor = codigos[0].rawValue;
                        cerrarCamara();

                        const match = await buscarProductoPorCodigoExacto(valor);
                        if (match) {
                            agregarProducto(match);
                        } else {
                            mostrarSinResultados(valor);
                            productoSearch.focus();
                        }
                        return;
                    }
                } catch (error) {
                    // Sigue intentando en el próximo frame.
                }

                frameCamara = requestAnimationFrame(detectarLoop);
            }

            function cerrarCamara() {
                if (frameCamara) {
                    cancelAnimationFrame(frameCamara);
                    frameCamara = null;
                }
                if (streamCamara) {
                    streamCamara.getTracks().forEach((track) => track.stop());
                    streamCamara = null;
                }
                if (camaraDialog.open) {
                    camaraDialog.close();
                }
            }

            escanearCamaraBtn.addEventListener('click', abrirCamara);
            cerrarCamaraBtn.addEventListener('click', cerrarCamara);
            // Por si se cierra con el Esc nativo del <dialog> en vez del
            // botón (el listener de document de arriba también lo cubre,
            // pero cerrarCamara() es idempotente — no pasa nada si corre
            // dos veces).
            camaraDialog.addEventListener('close', cerrarCamara);

            // Offline (ver CLAUDE.md, arquitectura offline y
            // resources/js/offline.js): antes de esto el submit era un POST
            // nativo del browser (el form ya tenía todos los hidden inputs
            // que necesita, ver render() arriba) — ahora se intercepta para
            // poder intentar un fetch primero y, si falla por falta de
            // conexión, encolar la venta en IndexedDB en vez de perderla.
            const CLASES_FEEDBACK = {
                exito: 'bg-[#f0fff2] dark:bg-[#00220a] border-[#03F53B] text-[#0a7d1e] dark:text-[#44FF66]',
                advertencia: 'bg-[#fffbea] dark:bg-[#2a2200] border-[#F5A623] text-[#8a6100] dark:text-[#F5C453]',
                error: 'bg-[#fff2f2] dark:bg-[#1D0002] border-[#F53003] text-[#F53003] dark:text-[#FF4433]',
            };

            function mostrarFeedback(tipo, mensaje) {
                ventaFeedback.className = `mb-4 rounded-sm border px-4 py-3 text-sm ${CLASES_FEEDBACK[tipo]}`;
                ventaFeedback.textContent = mensaje;
                ventaFeedback.hidden = false;
            }

            // Vacía el carrito (y el uuid_dispositivo ya usado, para que la
            // PRÓXIMA venta genere uno nuevo — ver enviarOEncolar en
            // offline.js) y deja la página lista para cargar la siguiente
            // venta sin navegar a ningún lado: es el flujo real de un
            // mostrador, una venta atrás de la otra.
            function resetearParaProximaVenta() {
                carrito = [];
                filaSeleccionada = null;
                const uuidInput = ventaForm.querySelector('input[name="uuid_dispositivo"]');
                if (uuidInput) {
                    uuidInput.remove();
                }
                montoEfectivoInput.value = '';
                clienteSelect.value = '';
                nuevoClienteForm.hidden = true;
                render();
                seleccionarMedioPago('efectivo');
                productoSearch.focus();
            }

            ventaForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (carrito.length === 0 || registrarBtn.disabled) {
                    return;
                }

                registrarBtn.disabled = true;
                ventaFeedback.hidden = true;

                const resultado = await window.offlineSync.enviarOEncolar(ventaForm, 'venta');

                if (resultado.estado === 'enviada') {
                    mostrarFeedback('exito', 'Venta registrada correctamente.');
                    resetearParaProximaVenta();
                    return;
                }

                if (resultado.estado === 'encolada') {
                    mostrarFeedback('advertencia', 'Venta guardada localmente, se va a sincronizar sola cuando vuelva la conexión.');
                    resetearParaProximaVenta();
                    return;
                }

                // 'error': el servidor respondió pero rechazó la venta (no es
                // un problema de conectividad — reintentar el mismo carrito
                // no lo arregla solo). Mostramos el detalle si vino como JSON
                // de validación; el carrito queda tal cual para corregirlo.
                let mensaje = 'No se pudo registrar la venta.';
                try {
                    const cuerpo = await resultado.response.json();
                    if (cuerpo && cuerpo.errors) {
                        mensaje = Object.values(cuerpo.errors).flat().join(' ');
                    }
                } catch (error) {
                    // La respuesta no era JSON: nos quedamos con el mensaje genérico.
                }

                mostrarFeedback('error', mensaje);
                actualizarEstadoBoton();
            });

            // Avisar antes de perder una venta a medio cargar. Dos capas,
            // porque no hay una sola API que cubra todas las formas de
            // "salir" de una página server-rendered como esta:
            //
            // 1) beforeunload: red de seguridad para lo que NO pasa por un
            //    <a> de acá adentro — cerrar la pestaña, escribir otra URL,
            //    recargar, atrás/adelante del navegador. Todos los
            //    navegadores modernos IGNORAN el mensaje custom por
            //    seguridad (anti-spam de popups) y muestran el suyo
            //    genérico — no hay forma de poner "esta venta no se va a
            //    registrar" ahí, es una limitación real del navegador.
            // 2) Click en cualquier <a> de la página: layouts/nav.blade.php
            //    se renderiza como parte de ESTE MISMO documento (Blade
            //    @@include), así que un solo listener delegado en document
            //    cubre el nav entero + "Volver a ventas" sin tocar el
            //    layout compartido. Acá SÍ podemos mostrar el modal propio
            //    con el mensaje real, porque interceptamos el click ANTES
            //    de que el navegador dispare la navegación (y por lo tanto
            //    antes de que dispare beforeunload).
            const confirmarSalirDialog = document.getElementById('confirmar-salir-dialog');
            const confirmarSalirBtn = document.getElementById('confirmar-salir-dialog-confirmar');
            const cancelarSalirBtn = document.getElementById('confirmar-salir-dialog-cancelar');
            const cantidadItemsCarritoSpan = document.getElementById('cantidad-items-carrito');
            const logoutForm = document.querySelector('form[action*="logout"]');
            let destinoPendiente = null;

            function hayVentaSinRegistrar() {
                return carrito.length > 0;
            }

            function avisarAntesDeSalir(event) {
                if (!hayVentaSinRegistrar()) {
                    return;
                }
                event.preventDefault();
                event.returnValue = ''; // requerido por Chrome para mostrar el prompt
            }
            window.addEventListener('beforeunload', avisarAntesDeSalir);

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');
                if (!link || !hayVentaSinRegistrar()) {
                    return;
                }

                event.preventDefault();
                destinoPendiente = link.href;
                cantidadItemsCarritoSpan.textContent = carrito.length;
                confirmarSalirDialog.showModal();
            });

            // "Salir" (logout) es un <form> que se manda con submit, no un
            // link — mismo aviso, mismo modal.
            if (logoutForm) {
                logoutForm.addEventListener('submit', (event) => {
                    if (!hayVentaSinRegistrar()) {
                        return;
                    }
                    event.preventDefault();
                    destinoPendiente = logoutForm;
                    cantidadItemsCarritoSpan.textContent = carrito.length;
                    confirmarSalirDialog.showModal();
                });
            }

            confirmarSalirBtn.addEventListener('click', () => {
                confirmarSalirDialog.close();

                // Sin esto, salir "de verdad" tras confirmar acá dispararía
                // TAMBIÉN el prompt nativo de beforeunload — dos avisos
                // seguidos por la misma salida ya confirmada.
                window.removeEventListener('beforeunload', avisarAntesDeSalir);

                if (destinoPendiente instanceof HTMLFormElement) {
                    destinoPendiente.submit();
                } else if (destinoPendiente) {
                    window.location.href = destinoPendiente;
                }
            });

            cancelarSalirBtn.addEventListener('click', () => {
                confirmarSalirDialog.close();
                destinoPendiente = null;
            });
        })();
    </script>
@endsection
