@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-lg font-medium">Clientes</h1>
        <a
            href="{{ route('clientes.create') }}"
            class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
        >
            Nuevo cliente
        </a>
    </div>

    <x-status-banner />
    <x-validation-errors />

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
@endsection
