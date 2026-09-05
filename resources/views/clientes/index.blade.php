<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - Clientes</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-lg font-medium">Clientes</h1>
            <a
                href="{{ route('clientes.create') }}"
                class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
            >
                Nuevo cliente
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

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <th class="py-2 pr-4">Nombre</th>
                        <th class="py-2 pr-4">Teléfono</th>
                        <th class="py-2 pr-4">Límite de crédito</th>
                        <th class="py-2 pr-4">Saldo (fiado)</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientes as $cliente)
                        {{-- saldo() se calcula UNA sola vez por fila y se pasa
                             a superaLimite() (en vez de reimplementar la regla
                             a mano acá, que podía desincronizarse del método
                             del modelo) para pintar la fila y mostrar el
                             número sin recalcular nada. --}}
                        @php
                            $saldo = $cliente->saldo();
                            $superaLimite = $cliente->superaLimite($saldo);
                        @endphp
                        <tr class="border-b border-[#19140035] dark:border-[#3E3E3A] {{ $superaLimite ? 'bg-[#fff2f2] dark:bg-[#1D0002]' : '' }}">
                            <td class="py-2 pr-4">{{ $cliente->nombre }}</td>
                            <td class="py-2 pr-4">{{ $cliente->telefono ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ number_format((float) $cliente->limite_credito, 2) }}</td>
                            <td class="py-2 pr-4 {{ $superaLimite ? 'text-[#F53003] dark:text-[#FF4433] font-medium' : ($saldo > 0 ? 'font-medium' : '') }}">
                                {{ number_format($saldo, 2) }}
                                @if ($superaLimite)
                                    <span class="ml-1 text-xs">(supera el límite)</span>
                                @endif
                            </td>
                            <td class="py-2">
                                <a href="{{ route('clientes.show', $cliente) }}" class="underline text-sm">Ver cuenta</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-sm opacity-70">
                                No hay clientes cargados todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
