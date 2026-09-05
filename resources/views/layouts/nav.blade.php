{{-- Header de navegación persistente: visible en toda página autenticada
     (el layout solo lo incluye dentro de @auth, así que /login nunca lo ve).
     La sección activa se resalta comparando contra el nombre de ruta actual
     (request()->routeIs()), no contra la URL, para que funcione igual con
     sub-rutas (productos.create, productos.edit, etc.). --}}
<nav class="border-b border-[#19140035] dark:border-[#3E3E3A]">
    <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between text-sm">
        <div class="flex items-center gap-6">
            <a
                href="{{ route('panel') }}"
                class="font-medium {{ request()->routeIs('panel') ? 'underline' : '' }}"
            >
                Panel
            </a>
            <a
                href="{{ route('productos.index') }}"
                class="{{ request()->routeIs('productos.*') ? 'underline font-medium' : '' }}"
            >
                Productos
            </a>
            <a
                href="{{ route('clientes.index') }}"
                class="{{ request()->routeIs('clientes.*') ? 'underline font-medium' : '' }}"
            >
                Clientes
            </a>
            <a
                href="{{ route('ventas.index') }}"
                class="{{ request()->routeIs('ventas.*') ? 'underline font-medium' : '' }}"
            >
                Ventas
            </a>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="underline">
                Salir
            </button>
        </form>
    </div>
</nav>
