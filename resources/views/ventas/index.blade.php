@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-lg font-medium">Ventas</h1>
        <a
            href="{{ route('ventas.create') }}"
            class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
        >
            Nueva venta
        </a>
    </div>

    <x-status-banner />

    {{-- Advertencia no bloqueante: cliente fiado que superó su límite de
         crédito (ver VentaController::store). La venta ya se registró;
         esto es solo un aviso para el kiosquero. --}}
    @if (session('advertencia'))
        <div class="mb-4 rounded-sm bg-[#fffbea] dark:bg-[#2a2200] border border-[#F5A623] text-[#8a6100] dark:text-[#F5C453] px-4 py-3 text-sm">
            {{ session('advertencia') }}
        </div>
    @endif

    <x-validation-errors />

    {{-- Anular es dueño-only (documento de alcance — corrección de error
         humano, ver VentaController::anular): un empleado ve el historial
         completo (columna "Estado" siempre visible, ninguna venta se
         esconde) pero no ve la acción, y el controller la bloquea igual
         por EnsureUserIsDueno aunque intente a mano por URL. --}}
    @php
        $esDueno = auth()->user()->esDueno();
    @endphp

    @if ($esDueno)
        <x-confirm-dialog
            id="confirmar-anular-venta-dialog"
            titulo="Anular venta"
            confirmar-label="Sí, anular venta"
            cancelar-label="Cancelar"
        >
            Esta acción revierte el stock de los productos vendidos y deja de contar la venta en reportes y en el
            saldo del cliente (si era fiada). La venta queda en el historial marcada como anulada — no se borra.

            <label for="motivo-anulacion-venta" class="block mt-3 text-xs opacity-70">Motivo (opcional)</label>
            <input
                type="text"
                id="motivo-anulacion-venta"
                maxlength="255"
                placeholder="Ej: cargada por error"
                class="mt-1 w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-2 py-1 text-sm"
            >
        </x-confirm-dialog>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                    <th class="py-2 pr-4">Fecha</th>
                    <th class="py-2 pr-4">Medio de pago</th>
                    <th class="py-2 pr-4">Cliente</th>
                    <th class="py-2 pr-4">Total</th>
                    <th class="py-2 pr-4">Estado</th>
                    @if ($esDueno)
                        <th class="py-2"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($ventas as $venta)
                    <tr class="border-b border-[#19140035] dark:border-[#3E3E3A] {{ $venta->estaAnulada() ? 'opacity-50' : '' }}">
                        <td class="py-2 pr-4">{{ $venta->created_at->format('d/m/Y H:i') }}</td>
                        <td class="py-2 pr-4 capitalize">{{ $venta->medio_pago }}</td>
                        <td class="py-2 pr-4">{{ $venta->cliente?->nombre ?? '—' }}</td>
                        <td class="py-2 pr-4 {{ $venta->estaAnulada() ? 'line-through' : '' }}">
                            {{ number_format((float) $venta->total, 2) }}
                        </td>
                        <td class="py-2 pr-4">
                            @if ($venta->estaAnulada())
                                <span
                                    class="rounded-sm bg-[#fff2f2] dark:bg-[#1D0002] text-[#F53003] dark:text-[#FF4433] px-2 py-0.5 text-xs font-medium"
                                    @if ($venta->motivo_anulacion) title="{{ $venta->motivo_anulacion }}" @endif
                                >
                                    Anulada
                                </span>
                            @endif
                        </td>
                        @if ($esDueno)
                            <td class="py-2">
                                @unless ($venta->estaAnulada())
                                    <form method="POST" action="{{ route('ventas.anular', $venta) }}" class="anular-venta-form">
                                        @csrf
                                        <button type="button" class="anular-venta-btn underline text-sm text-[#F53003] dark:text-[#FF4433]">
                                            Anular
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $esDueno ? 6 : 5 }}" class="py-4 text-center text-sm opacity-70">
                            No hay ventas registradas todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($esDueno)
        <script>
            (function () {
                const dialog = document.getElementById('confirmar-anular-venta-dialog');
                const confirmarBtn = document.getElementById('confirmar-anular-venta-dialog-confirmar');
                const cancelarBtn = document.getElementById('confirmar-anular-venta-dialog-cancelar');
                const motivoInput = document.getElementById('motivo-anulacion-venta');

                let formAAnular = null;

                document.querySelectorAll('.anular-venta-btn').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        formAAnular = btn.closest('form');
                        motivoInput.value = '';
                        dialog.showModal();
                    });
                });

                confirmarBtn.addEventListener('click', () => {
                    dialog.close();

                    if (!formAAnular) {
                        return;
                    }

                    let motivoHidden = formAAnular.querySelector('input[name="motivo_anulacion"]');
                    if (!motivoHidden) {
                        motivoHidden = document.createElement('input');
                        motivoHidden.type = 'hidden';
                        motivoHidden.name = 'motivo_anulacion';
                        formAAnular.appendChild(motivoHidden);
                    }
                    motivoHidden.value = motivoInput.value;

                    formAAnular.submit();
                });

                cancelarBtn.addEventListener('click', () => dialog.close());
            })();
        </script>
    @endif
@endsection
