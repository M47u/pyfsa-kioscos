@extends('layouts.app')

@section('title', 'Panel')

@section('content')
    <h1 class="text-lg font-medium mb-2">Bienvenido</h1>
    <p class="text-sm opacity-70 mb-6">Comercio: {{ $comercioNombre }}</p>

    <x-status-banner />

    {{-- Operación: lo que un empleado también puede usar — mismos permisos
         reales que layouts/nav.blade.php, esto es solo el resumen visual.
         Antes de esta separación, las tarjetas de Artículos/Reportes se
         mostraban a CUALQUIER usuario (sin chequear esDueno()) aunque esas
         rutas son dueño-only — un empleado las veía acá y se encontraba
         con un 403 recién al hacer click. Bug real, corregido de paso. --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <a
            href="{{ route('ventas.index') }}"
            class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
        >
            <h2 class="text-sm font-medium mb-1">Ventas</h2>
            <p class="text-sm opacity-70">Ver historial y registrar una venta nueva.</p>
        </a>

        <a
            href="{{ route('caja.show') }}"
            class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
        >
            <h2 class="text-sm font-medium mb-1">Caja</h2>
            <p class="text-sm opacity-70">Abrir/cerrar turno y registrar ingresos o egresos.</p>
        </a>

        <a
            href="{{ route('clientes.index') }}"
            class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
        >
            <h2 class="text-sm font-medium mb-1">Clientes</h2>
            <p class="text-sm opacity-70">Ver cuentas corrientes y registrar pagos.</p>
        </a>
    </div>

    {{-- Administración: dueño-only, mismo criterio que layouts/nav.blade.php. --}}
    @if (auth()->user()->esDueno())
        <p class="text-xs font-medium opacity-50 mb-2">Administración</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <a
                href="{{ route('productos.index') }}"
                class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
            >
                <h2 class="text-sm font-medium mb-1">Artículos</h2>
                <p class="text-sm opacity-70">Ver stock y cargar artículos nuevos.</p>
            </a>

            <a
                href="{{ route('reportes.index') }}"
                class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
            >
                <h2 class="text-sm font-medium mb-1">Reportes</h2>
                <p class="text-sm opacity-70">Ventas de la semana, cuentas por cobrar y stock bajo mínimo.</p>
            </a>

            <a
                href="{{ route('usuarios.index') }}"
                class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 hover:bg-[#f5f5f4] dark:hover:bg-[#161615]"
            >
                <h2 class="text-sm font-medium mb-1">Usuarios</h2>
                <p class="text-sm opacity-70">Alta de empleados, restablecer contraseña y eliminar acceso.</p>
            </a>
        </div>
    @endif
@endsection
