@extends('layouts.app')

@section('title', 'Nueva venta')

@section('body-class', 'p-6')
@section('container-class', 'max-w-2xl mx-auto')

@section('content')
    <h1 class="text-lg font-medium mb-6">Nueva venta</h1>

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
            <label for="producto-search" class="block text-sm font-medium mb-1">Buscar o escanear producto</label>
            {{--
                Input de texto, no <select>: un lector de código de barras
                USB/Bluetooth "tipea" el código acá adentro y manda un Enter
                solo, sin integración especial (ver documento de alcance).
                Un <select> con el catálogo entero tampoco es usable pasados
                unos pocos cientos de productos.
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
            </div>
            <p id="producto-sin-resultados" hidden class="mt-1 text-xs opacity-70">
                No se encontró ningún producto con ese nombre o código.
            </p>
        </div>

        <div>
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <th class="py-2 pr-4">Producto</th>
                        <th class="py-2 pr-4">Cantidad</th>
                        <th class="py-2 pr-4">Precio unit.</th>
                        <th class="py-2 pr-4">Subtotal</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody id="carrito-body"></tbody>
            </table>
            <p id="carrito-vacio-msg" class="py-4 text-center text-sm opacity-70">
                El carrito está vacío.
            </p>
            <div id="items-inputs"></div>
        </div>

        <div class="text-right text-base font-medium">
            Total: $<span id="total-venta">0.00</span>
        </div>

        {{-- Recién acá, con los artículos ya cargados, se elige cómo se cobra. --}}
        <div class="border-t border-[#19140035] dark:border-[#3E3E3A] pt-4">
            <label for="medio_pago" class="block text-sm font-medium mb-1">Medio de pago</label>
            <select
                id="medio_pago"
                name="medio_pago"
                required
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
                <option value="efectivo">Efectivo</option>
                <option value="transferencia">Transferencia</option>
                <option value="fiado">Fiado</option>
            </select>
        </div>

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
        </div>

        {{-- Solo con "Efectivo": calculadora de vuelto. Deliberadamente sin
             `name` — es una ayuda para el cajero, no un dato que la venta
             guarde (no está en el alcance original ni se pidió persistirlo);
             sin `name` el navegador nunca lo manda en el POST/FormData, así
             que no hace falta tocar VentaRequest ni offline.js para nada. --}}
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
        </div>

        <button
            type="submit"
            id="registrar-venta-btn"
            disabled
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium disabled:opacity-50"
        >
            Registrar venta
        </button>
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

    <script>
        (function () {
            const ventaForm = document.getElementById('venta-form');
            const ventaFeedback = document.getElementById('venta-feedback');
            const medioPagoSelect = document.getElementById('medio_pago');
            const clienteWrapper = document.getElementById('cliente-wrapper');
            const productoSearch = document.getElementById('producto-search');
            const productoResultados = document.getElementById('producto-resultados');
            const productoSinResultados = document.getElementById('producto-sin-resultados');
            const cantidadInput = document.getElementById('cantidad-input');
            const carritoBody = document.getElementById('carrito-body');
            const carritoVacioMsg = document.getElementById('carrito-vacio-msg');
            const itemsInputs = document.getElementById('items-inputs');
            const totalSpan = document.getElementById('total-venta');
            const registrarBtn = document.getElementById('registrar-venta-btn');
            const montoEfectivoWrapper = document.getElementById('monto-efectivo-wrapper');
            const montoEfectivoInput = document.getElementById('monto-efectivo-input');
            const vueltoSpan = document.getElementById('vuelto-venta');

            let carrito = [];
            let resultadosActuales = [];
            let totalActual = 0;

            function debounce(fn, ms) {
                let timeoutId;
                return (...args) => {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => fn(...args), ms);
                };
            }

            function agregarProducto(producto) {
                const cantidad = parseInt(cantidadInput.value, 10);
                if (!producto || !cantidad || cantidad < 1) {
                    return;
                }

                carrito.push({
                    productoId: producto.id,
                    nombre: producto.nombre,
                    precio: producto.precio_venta,
                    cantidad,
                });

                productoSearch.value = '';
                cantidadInput.value = 1;
                ocultarResultados();
                render();
                productoSearch.focus();
            }

            function ocultarResultados() {
                resultadosActuales = [];
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
                resultadosActuales = productos;
                productoSinResultados.hidden = productos.length !== 0;
                productoResultados.hidden = productos.length === 0;
                productoResultados.innerHTML = '';

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

                const respuesta = await fetch(`{{ route('productos.index') }}?buscar=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                });
                mostrarResultados(await respuesta.json());
            }, 200);

            productoSearch.addEventListener('input', () => buscarProductos(productoSearch.value.trim()));

            // El lector de código de barras "tipea" el código y manda un
            // Enter solo, casi sin pausa. NO reusamos resultadosActuales acá:
            // ese estado lo llena el buscador debounced (200ms) del evento
            // 'input', que a esa velocidad puede no haber resuelto todavía
            // (o haber resuelto para un código a medio escribir) — mostraría
            // el dropdown de sugerencias en vez de resolver directo. Enter
            // dispara su propia búsqueda inmediata y agrega el producto cuyo
            // código de barras coincide EXACTO con lo tipeado; si no hay
            // coincidencia exacta de código pero la búsqueda deja un único
            // resultado (alguien tipeando un nombre a mano), se agrega ese.
            // Nunca deja el dropdown de sugerencias como resultado del Enter.
            productoSearch.addEventListener('keydown', async (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault(); // no confundir con el submit del form de la venta

                const term = productoSearch.value.trim();
                if (term === '') {
                    return;
                }

                const respuesta = await fetch(`{{ route('productos.index') }}?buscar=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                });
                const productos = await respuesta.json();
                const match = productos.find((producto) => producto.codigo_barras === term) ??
                    (productos.length === 1 ? productos[0] : null);

                ocultarResultados();

                if (match) {
                    agregarProducto(match);
                } else {
                    productoSinResultados.hidden = false;
                }
            });

            // Vuelto = lo que abona el cliente en efectivo - el total de la
            // venta. Se recalcula acá (input del monto) Y desde render() de
            // abajo (el total cambia cada vez que se agrega/quita un
            // artículo) — ambos casos convergen en esta única función para
            // no duplicar la cuenta en dos lugares.
            function actualizarVuelto() {
                const montoEfectivo = parseFloat(montoEfectivoInput.value);
                const vuelto = (isNaN(montoEfectivo) ? 0 : montoEfectivo) - totalActual;

                vueltoSpan.textContent = vuelto.toFixed(2);
                vueltoSpan.classList.toggle('text-[#F53003]', vuelto < 0);
                vueltoSpan.classList.toggle('dark:text-[#FF4433]', vuelto < 0);
            }

            montoEfectivoInput.addEventListener('input', actualizarVuelto);

            function toggleMedioPago() {
                clienteWrapper.hidden = medioPagoSelect.value !== 'fiado';

                montoEfectivoWrapper.hidden = medioPagoSelect.value !== 'efectivo';
                if (!montoEfectivoWrapper.hidden) {
                    actualizarVuelto();
                }
            }
            medioPagoSelect.addEventListener('change', toggleMedioPago);
            toggleMedioPago();

            function render() {
                carritoBody.innerHTML = '';
                itemsInputs.innerHTML = '';
                let total = 0;

                carrito.forEach((item, index) => {
                    const subtotal = item.precio * item.cantidad;
                    total += subtotal;

                    // textContent para item.nombre (viene de la base, no es
                    // HTML de confianza) en vez de interpolarlo en innerHTML
                    // — mismo criterio que mostrarResultados() más arriba.
                    const row = document.createElement('tr');
                    row.className = 'border-b border-[#19140035] dark:border-[#3E3E3A]';

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

                    itemsInputs.insertAdjacentHTML('beforeend', `
                        <input type="hidden" name="items[${index}][producto_id]" value="${item.productoId}">
                        <input type="hidden" name="items[${index}][cantidad]" value="${item.cantidad}">
                    `);
                });

                carritoVacioMsg.hidden = carrito.length > 0;
                totalActual = total;
                totalSpan.textContent = total.toFixed(2);
                registrarBtn.disabled = carrito.length === 0;

                if (!montoEfectivoWrapper.hidden) {
                    actualizarVuelto();
                }

                // 'change' (blur/Enter), no 'input': cada cambio dispara
                // render(), que reconstruye el <tbody> entero — si
                // reaccionara a cada tecla, el input perdería el foco a
                // mitad de tipear un número de más de un dígito.
                carritoBody.querySelectorAll('.cantidad-item').forEach((input) => {
                    input.addEventListener('change', () => {
                        const index = Number(input.dataset.index);
                        const cantidad = parseInt(input.value, 10);

                        if (!cantidad || cantidad < 1) {
                            input.value = carrito[index].cantidad; // revierte, no deja cantidad inválida
                            return;
                        }

                        carrito[index].cantidad = cantidad;
                        render();
                    });
                });

                carritoBody.querySelectorAll('.quitar-item').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        carrito.splice(Number(btn.dataset.index), 1);
                        render();
                    });
                });
            }

            render();

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
                const uuidInput = ventaForm.querySelector('input[name="uuid_dispositivo"]');
                if (uuidInput) {
                    uuidInput.remove();
                }
                montoEfectivoInput.value = '';
                render();
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
                registrarBtn.disabled = carrito.length === 0;
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
