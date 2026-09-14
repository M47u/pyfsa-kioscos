@extends('layouts.app')

@section('title', 'Artículos')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-lg font-medium">Artículos</h1>
        <div class="flex items-center gap-3">
            <a
                href="{{ route('productos.importar') }}"
                class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-2 text-sm font-medium"
            >
                Importar CSV
            </a>
            <a
                href="{{ route('productos.create') }}"
                class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
            >
                Nuevo artículo
            </a>
        </div>
    </div>

    <x-status-banner />
    <x-validation-errors />

    {{-- Link desde el resumen de stock bajo mínimo en Reportes (ver
         ReporteController): ?bajo_minimo=1 filtra el listado a solo esos
         productos. Se preserva con un hidden al buscar por nombre/código
         para que ambos filtros puedan combinarse en vez de que buscar
         pise silenciosamente el filtro anterior. --}}
    @if ($bajoMinimo)
        <div class="mb-4 flex items-center justify-between rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-[#f5f5f4] dark:bg-[#161615] px-3 py-2 text-sm">
            <span>Mostrando solo artículos bajo el stock mínimo.</span>
            <a href="{{ route('productos.index', ['buscar' => $buscar]) }}" class="underline">Quitar filtro</a>
        </div>
    @endif

    <form method="GET" action="{{ route('productos.index') }}" class="mb-6 flex gap-2">
        @if ($bajoMinimo)
            <input type="hidden" name="bajo_minimo" value="1">
        @endif
        <input
            type="text"
            name="buscar"
            value="{{ $buscar }}"
            placeholder="Buscar por nombre o código de barras..."
            autofocus
            class="flex-1 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
        >
        <button
            type="submit"
            class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-2 text-sm font-medium"
        >
            Buscar
        </button>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                    <th class="py-2 pr-4">Nombre</th>
                    <th class="py-2 pr-4">Código de barras</th>
                    <th class="py-2 pr-4">Precio costo</th>
                    <th class="py-2 pr-4">Precio venta</th>
                    <th class="py-2 pr-4">Stock actual</th>
                    <th class="py-2 pr-4">Mínimo</th>
                    <th class="py-2 pr-4">Reponer</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($productos as $producto)
                    @php
                        $stockActual = $producto->stockActual();
                        $bajoMinimo = $stockActual < $producto->stock_minimo;
                    @endphp
                    <tr class="border-b border-[#19140035] dark:border-[#3E3E3A] {{ $bajoMinimo ? 'bg-[#fff2f2] dark:bg-[#1D0002]' : '' }}">
                        <td class="py-2 pr-4">{{ $producto->nombre }}</td>
                        <td class="py-2 pr-4">{{ $producto->codigo_barras ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ number_format((float) $producto->precio_costo, 2) }}</td>
                        <td class="py-2 pr-4">{{ number_format((float) $producto->precio_venta, 2) }}</td>
                        <td class="py-2 pr-4 {{ $bajoMinimo ? 'text-[#F53003] dark:text-[#FF4433] font-medium' : '' }}">
                            {{ $stockActual }}
                            @if ($bajoMinimo)
                                <span class="ml-1 text-xs">(bajo mínimo)</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4">{{ $producto->stock_minimo }}</td>
                        <td class="py-2 pr-4">
                            <form method="POST" action="{{ route('productos.reponer', $producto) }}" class="flex gap-1">
                                @csrf
                                <input
                                    type="number"
                                    inputmode="numeric"
                                    name="cantidad"
                                    min="1"
                                    placeholder="Cant."
                                    required
                                    class="w-20 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-2 py-1 text-sm"
                                >
                                <button
                                    type="submit"
                                    class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-3 py-1 text-sm font-medium"
                                >
                                    Reponer
                                </button>
                            </form>
                        </td>
                        <td class="py-2">
                            <a href="{{ route('productos.edit', $producto) }}" class="underline text-sm">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-4 text-center text-sm opacity-70">
                            No hay artículos cargados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
