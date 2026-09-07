{{-- Nav mínimo del panel admin de PyFsa (/admin, ver routes/web.php) —
     contraparte de layouts.nav para cuando NO hay tenancy inicializada.
     Sin secciones de comercio (Productos/Clientes/Ventas/Reportes): acá no
     hay un comercio en contexto, sería mostrar links a nada. --}}
<nav class="border-b border-[#19140035] dark:border-[#3E3E3A]">
    <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between text-sm">
        <a
            href="{{ route('admin.comercios.index') }}"
            class="font-medium {{ request()->routeIs('admin.comercios.*') ? 'underline' : '' }}"
        >
            Comercios
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="underline">
                Salir
            </button>
        </form>
    </div>
</nav>
