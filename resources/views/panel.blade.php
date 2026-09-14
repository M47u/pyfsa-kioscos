@extends('layouts.app')

@section('title', 'Panel')

@section('content')
    <h1 class="text-lg font-medium mb-2">Bienvenido</h1>
    <p class="text-sm opacity-70 mb-6">Comercio: {{ $comercioNombre }}</p>

    <x-status-banner />

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <a
            href="{{ route('productos.index') }}"
            class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
        >
            <h2 class="text-sm font-medium mb-1">Artículos</h2>
            <p class="text-sm opacity-70">Ver stock y cargar artículos nuevos.</p>
        </a>

        <a
            href="{{ route('clientes.index') }}"
            class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
        >
            <h2 class="text-sm font-medium mb-1">Clientes</h2>
            <p class="text-sm opacity-70">Ver cuentas corrientes y registrar pagos.</p>
        </a>

        <a
            href="{{ route('ventas.index') }}"
            class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
        >
            <h2 class="text-sm font-medium mb-1">Ventas</h2>
            <p class="text-sm opacity-70">Ver historial y registrar una venta nueva.</p>
        </a>

        <a
            href="{{ route('reportes.index') }}"
            class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
        >
            <h2 class="text-sm font-medium mb-1">Reportes</h2>
            <p class="text-sm opacity-70">Ventas de la semana, cuentas por cobrar y stock bajo mínimo.</p>
        </a>

        {{-- Dueño-only, mismo criterio que layouts/nav.blade.php: el
             empleado no gestiona empleados. --}}
        @if (auth()->user()->esDueno())
            <a
                href="{{ route('usuarios.index') }}"
                class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
            >
                <h2 class="text-sm font-medium mb-1">Usuarios</h2>
                <p class="text-sm opacity-70">Alta de empleados, restablecer contraseña y eliminar acceso.</p>
            </a>
        @endif
    </div>
@endsection
