@extends('layouts.app')

@section('title', 'Nueva venta')

@section('body-class', 'p-6')
@section('container-class', 'max-w-2xl mx-auto')

@section('content')
    <h1 class="text-lg font-medium mb-6">Nueva venta</h1>

    <x-validation-errors />

    <form method="POST" action="{{ route('ventas.store') }}" id="venta-form" class="space-y-6">
        @csrf

        <div>
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

        <div class="border-t border-[#19140035] dark:border-[#3E3E3A] pt-4">
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

    <script>
        (function () {
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

            let carrito = [];
            let resultadosActuales = [];

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

            function toggleCliente() {
                clienteWrapper.hidden = medioPagoSelect.value !== 'fiado';
            }
            medioPagoSelect.addEventListener('change', toggleCliente);
            toggleCliente();

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
                        <td class="py-2 pr-4">${item.cantidad}</td>
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
                totalSpan.textContent = total.toFixed(2);
                registrarBtn.disabled = carrito.length === 0;

                carritoBody.querySelectorAll('.quitar-item').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        carrito.splice(Number(btn.dataset.index), 1);
                        render();
                    });
                });
            }

            render();
        })();
    </script>
@endsection
