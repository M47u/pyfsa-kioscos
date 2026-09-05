<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - Productos</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-lg font-medium">Productos</h1>
            <a
                href="{{ route('productos.create') }}"
                class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
            >
                Nuevo producto
            </a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-sm bg-[#f0fff2] dark:bg-[#00220a] border border-[#03F53B] text-[#0a7d1e] dark:text-[#44FF66] px-4 py-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-sm bg-[#fff2f2] dark:bg-[#1D0002] border border-[#F53003] text-[#F53003] dark:text-[#FF4433] px-4 py-3 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('productos.index') }}" class="mb-6 flex gap-2">
            <input
                type="text"
                name="buscar"
                value="{{ $buscar }}"
                placeholder="Buscar por nombre o código de barras..."
                autofocus
                class="flex-1 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
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
                                        name="cantidad"
                                        min="1"
                                        placeholder="Cant."
                                        required
                                        class="w-20 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-2 py-1 text-sm"
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
                                No hay productos cargados todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
