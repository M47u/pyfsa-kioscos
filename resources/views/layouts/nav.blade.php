{{-- Header de navegación persistente: visible en toda página autenticada
     (el layout solo lo incluye dentro de @auth, así que /login nunca lo ve).
     La sección activa se resalta comparando contra el nombre de ruta actual
     (request()->routeIs()), no contra la URL, para que funcione igual con
     sub-rutas (productos.create, productos.edit, etc.).

     Responsive sin JS propio: <details>/<summary> es un disclosure nativo
     del navegador — el ☰ de mobile no necesita ni una línea de JavaScript
     para abrir/cerrar (clickear un link navega y listo, no hace falta
     cerrarlo a mano). Los mismos 4 links se listan una sola vez en
     $secciones y se pintan dos veces (fila horizontal desde sm:, columna
     colapsable antes de eso) para que agregar una sección no signifique
     tocar el markup en dos lugares. --}}
@php
    $secciones = [
        ['ruta' => 'productos.index', 'activo' => 'productos.*', 'label' => 'Productos'],
        ['ruta' => 'clientes.index', 'activo' => 'clientes.*', 'label' => 'Clientes'],
        ['ruta' => 'ventas.index', 'activo' => 'ventas.*', 'label' => 'Ventas'],
        ['ruta' => 'reportes.index', 'activo' => 'reportes.*', 'label' => 'Reportes'],
    ];
@endphp

<nav class="border-b border-[#19140035] dark:border-[#3E3E3A]">
    <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between text-sm">
        <a
            href="{{ route('panel') }}"
            class="font-medium {{ request()->routeIs('panel') ? 'underline' : '' }}"
        >
            Panel
        </a>

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
