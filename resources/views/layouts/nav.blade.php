{{-- Header de navegación persistente: visible en toda página autenticada
     (el layout solo lo incluye dentro de @auth, así que /login nunca lo ve).
     La sección activa se resalta comparando contra el nombre de ruta actual
     (request()->routeIs()), no contra la URL, para que funcione igual con
     sub-rutas (productos.create, productos.edit, etc.).

     Responsive sin JS propio: <details>/<summary> es un disclosure nativo
     del navegador — el ☰ de mobile no necesita ni una línea de JavaScript
     para abrir/cerrar (clickear un link navega y listo, no hace falta
     cerrarlo a mano). Los mismos links se listan una sola vez en
     $secciones y se pintan dos veces (fila horizontal desde sm:, columna
     colapsable antes de eso) para que agregar una sección no signifique
     tocar el markup en dos lugares.

     Roles (documento de alcance, módulo 3.5 "Usuarios"): Productos,
     Reportes y Usuarios son dueño-only (mismo gate que EnsureUserIsDueno
     en routes/tenant.php) — un empleado solo ve Ventas y Clientes acá. --}}
@php
    $secciones = collect([
        ['ruta' => 'productos.index', 'activo' => 'productos.*', 'label' => 'Productos', 'dueno' => true],
        ['ruta' => 'clientes.index', 'activo' => 'clientes.*', 'label' => 'Clientes', 'dueno' => false],
        ['ruta' => 'ventas.index', 'activo' => 'ventas.*', 'label' => 'Ventas', 'dueno' => false],
        ['ruta' => 'reportes.index', 'activo' => 'reportes.*', 'label' => 'Reportes', 'dueno' => true],
        ['ruta' => 'usuarios.index', 'activo' => 'usuarios.*', 'label' => 'Usuarios', 'dueno' => true],
    ])->filter(fn ($seccion) => ! $seccion['dueno'] || auth()->user()->esDueno());
@endphp

<nav class="border-b border-[#19140035] dark:border-[#3E3E3A]">
    <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between text-sm">
        <div class="flex items-center gap-3">
            <a
                href="{{ route('panel') }}"
                class="font-medium {{ request()->routeIs('panel') ? 'underline' : '' }}"
            >
                Panel
            </a>

            {{-- Offline (ver CLAUDE.md, arquitectura offline y
                 resources/js/offline.js): "Sin conexión" cuando el
                 navegador no tiene señal, y/o la cantidad de ventas/pagos
                 todavía sin sincronizar de la cola de IndexedDB. Arranca
                 hidden y el script de abajo decide si mostrarlo — sin JS
                 (navegador viejo, script bloqueado) simplemente no aparece,
                 no rompe nada del resto del nav. --}}
            <span id="offline-status-badge" hidden class="rounded-sm bg-[#fffbea] dark:bg-[#2a2200] border border-[#F5A623] text-[#8a6100] dark:text-[#F5C453] px-2 py-0.5 text-xs font-medium"></span>
        </div>

        {{-- Fila horizontal, solo desde sm: (640px) para arriba. --}}
        <div class="hidden sm:flex items-center gap-6">
            @foreach ($secciones as $seccion)
                <a
                    href="{{ route($seccion['ruta']) }}"
                    class="{{ request()->routeIs($seccion['activo']) ? 'underline font-medium' : '' }}"
                >
                    {{ $seccion['label'] }}
                </a>
            @endforeach

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="underline">
                    Salir
                </button>
            </form>
        </div>

        {{-- Menú ☰, solo debajo de sm:. list-none saca el triángulo default
             del <summary> — el ☰ ya cumple esa función. --}}
        <details class="sm:hidden relative">
            <summary class="list-none cursor-pointer select-none px-3 py-1.5 rounded-sm border border-[#19140035] dark:border-[#3E3E3A]">
                ☰
            </summary>

            <div class="absolute right-0 mt-2 w-44 flex flex-col rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-[#FDFDFC] dark:bg-[#0a0a0a] shadow-lg z-20 overflow-hidden">
                @foreach ($secciones as $seccion)
                    <a
                        href="{{ route($seccion['ruta']) }}"
                        class="px-4 py-2 hover:bg-[#f5f5f4] dark:hover:bg-[#161615] {{ request()->routeIs($seccion['activo']) ? 'underline font-medium' : '' }}"
                    >
                        {{ $seccion['label'] }}
                    </a>
                @endforeach

                <form method="POST" action="{{ route('logout') }}" class="border-t border-[#19140035] dark:border-[#3E3E3A]">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 underline hover:bg-[#f5f5f4] dark:hover:bg-[#161615]">
                        Salir
                    </button>
                </form>
            </div>
        </details>
    </div>
</nav>

<script>
    (function () {
        const badge = document.getElementById('offline-status-badge');
        if (!badge) {
            return;
        }

        function pintar(cantidadPendientes) {
            if (!navigator.onLine) {
                badge.hidden = false;
                badge.textContent = cantidadPendientes > 0
                    ? `Sin conexión — ${cantidadPendientes} pendiente(s)`
                    : 'Sin conexión';
                return;
            }

            if (cantidadPendientes > 0) {
                badge.hidden = false;
                badge.textContent = `${cantidadPendientes} pendiente(s) de sincronizar`;
                return;
            }

            badge.hidden = true;
        }

        // window.offlineSync lo define resources/js/offline.js (importado
        // desde app.js, un <script type="module"> que Vite difiere hasta
        // después de parsear todo el HTML) — recién puede no existir
        // todavía en este punto, por eso se espera a 'load', que corre
        // después de que los módulos ya terminaron de ejecutar.
        function actualizar() {
            if (!window.offlineSync) {
                pintar(0);
                return;
            }

            window.offlineSync.contarPendientes().then(pintar).catch(() => pintar(0));
        }

        window.addEventListener('load', actualizar);
        window.addEventListener('online', actualizar);
        window.addEventListener('offline', actualizar);
        window.addEventListener('offlinequeuechange', (event) => pintar(event.detail.cantidad));
    })();
</script>
