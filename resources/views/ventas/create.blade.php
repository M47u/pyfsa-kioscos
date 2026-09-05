<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - Nueva venta</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen p-6">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-lg font-medium mb-6">Nueva venta</h1>

        @if ($errors->any())
            <div class="mb-4 rounded-sm bg-[#fff2f2] dark:bg-[#1D0002] border border-[#F53003] text-[#F53003] dark:text-[#FF4433] px-4 py-3 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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
                <label class="block text-sm font-medium mb-1">Agregar producto</label>
                <div class="flex gap-2">
                    <select
                        id="producto-select"
                        class="flex-1 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                    >
                        <option value="">-- Elegir producto --</option>
                        @foreach ($productos as $producto)
                            <option
                                value="{{ $producto->id }}"
                                data-nombre="{{ $producto->nombre }}"
                                data-precio="{{ $producto->precio_venta }}"
                            >
                                {{ $producto->nombre }} ({{ $producto->codigo_barras ?? 's/código' }}) — ${{ number_format((float) $producto->precio_venta, 2) }}
                            </option>
                        @endforeach
                    </select>
                    <input
                        type="number"
                        id="cantidad-input"
                        min="1"
                        value="1"
                        class="w-20 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-2 py-2 text-sm"
                    >
                    <button
                        type="button"
                        id="agregar-item-btn"
                        class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-2 text-sm font-medium"
                    >
                        Agregar
                    </button>
                </div>
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
    </div>

    <script>
        (function () {
            const medioPagoSelect = document.getElementById('medio_pago');
            const clienteWrapper = document.getElementById('cliente-wrapper');
            const productoSelect = document.getElementById('producto-select');
            const cantidadInput = document.getElementById('cantidad-input');
            const agregarBtn = document.getElementById('agregar-item-btn');
            const carritoBody = document.getElementById('carrito-body');
            const carritoVacioMsg = document.getElementById('carrito-vacio-msg');
            const itemsInputs = document.getElementById('items-inputs');
            const totalSpan = document.getElementById('total-venta');
            const registrarBtn = document.getElementById('registrar-venta-btn');

            let carrito = [];

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

                    const row = document.createElement('tr');
                    row.className = 'border-b border-[#19140035] dark:border-[#3E3E3A]';
                    row.innerHTML = `
                        <td class="py-2 pr-4">${item.nombre}</td>
                        <td class="py-2 pr-4">${item.cantidad}</td>
                        <td class="py-2 pr-4">${item.precio.toFixed(2)}</td>
                        <td class="py-2 pr-4">${subtotal.toFixed(2)}</td>
                        <td class="py-2"><button type="button" data-index="${index}" class="quitar-item underline text-sm">Quitar</button></td>
                    `;
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

            agregarBtn.addEventListener('click', () => {
                const option = productoSelect.selectedOptions[0];
                if (!option || !option.value) {
                    return;
                }

                const cantidad = parseInt(cantidadInput.value, 10);
                if (!cantidad || cantidad < 1) {
                    return;
                }

                carrito.push({
                    productoId: option.value,
                    nombre: option.dataset.nombre,
                    precio: parseFloat(option.dataset.precio),
                    cantidad,
                });

                productoSelect.value = '';
                cantidadInput.value = 1;
                render();
            });

            render();
        })();
    </script>
</body>
</html>
