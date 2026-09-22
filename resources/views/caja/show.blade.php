@extends('layouts.app')

@section('title', 'Caja')

@section('content')
    <h1 class="text-lg font-medium mb-6">Caja</h1>

    <x-status-banner />
    <x-validation-errors />

    @if ($caja === null)
        {{-- Sin caja abierta: solo se puede abrir un turno nuevo. --}}
        <div class="mb-6 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4 max-w-sm">
            <h2 class="text-sm font-medium mb-3">Abrir caja</h2>
            <form method="POST" action="{{ route('caja.abrir') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="monto_apertura" class="block text-sm font-medium mb-1">Monto inicial en efectivo</label>
                    <input
                        type="number"
                        id="monto_apertura"
                        name="monto_apertura"
                        step="0.01"
                        min="0"
                        value="{{ old('monto_apertura', 0) }}"
                        required
                        autofocus
                        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                    >
                </div>
                <button
                    type="submit"
                    class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
                >
                    Abrir caja
                </button>
            </form>
        </div>
    @else
        {{-- Resumen en vivo del turno abierto — mismo criterio de "calcular,
             no guardar acumulado" que Producto::stockActual()/Cliente::saldo():
             nada de esto se persiste hasta el cierre (ver Caja::efectivoEsperadoActual()). --}}
        <div class="mb-6 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <p class="text-xs opacity-70 mb-3">
                Turno abierto desde {{ $caja->abierta_en->format('d/m/Y H:i') }}
            </p>

            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-y-2 text-sm">
                <dt class="opacity-70">Apertura</dt>
                <dd class="sm:col-span-2">{{ number_format((float) $caja->monto_apertura, 2) }}</dd>

                @foreach (\App\Models\Venta::ETIQUETAS_MEDIO_PAGO as $medio => $etiqueta)
                    <dt class="opacity-70">Ventas — {{ $etiqueta }}</dt>
                    <dd class="sm:col-span-2">{{ number_format($ventasPorMedioPago[$medio] ?? 0, 2) }}</dd>
                @endforeach

                <dt class="opacity-70">Cobros de cuenta corriente</dt>
                <dd class="sm:col-span-2">{{ number_format($totalPagos, 2) }}</dd>

                <dt class="opacity-70">Ingresos manuales</dt>
                <dd class="sm:col-span-2 text-[#0a7d1e] dark:text-[#44FF66]">+{{ number_format($totalIngresos, 2) }}</dd>

                <dt class="opacity-70">Egresos manuales</dt>
                <dd class="sm:col-span-2 text-[#F53003] dark:text-[#FF4433]">-{{ number_format($totalEgresos, 2) }}</dd>

                <dt class="font-medium">Efectivo esperado</dt>
                <dd class="sm:col-span-2 font-medium">{{ number_format($efectivoEsperado, 2) }}</dd>
            </dl>

            <p class="mt-3 text-xs opacity-60">
                Cobros de cuenta corriente asumidos en efectivo — el sistema todavía no distingue el medio de pago
                de un cobro de fiado.
            </p>
        </div>

        {{-- Ingreso/egreso manual (ej. retiro para cambio, pago a un
             proveedor en efectivo) — sin confirmación extra, es una acción
             de bajo riesgo y frecuente durante el turno. --}}
        <div class="mb-6 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Registrar ingreso o egreso</h2>
            <form method="POST" action="{{ route('caja.movimientos.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                @csrf
                <select
                    name="tipo"
                    required
                    class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                >
                    <option value="{{ \App\Models\MovimientoCaja::TIPO_INGRESO }}">Ingreso</option>
                    <option value="{{ \App\Models\MovimientoCaja::TIPO_EGRESO }}">Egreso</option>
                </select>
                <input
                    type="number"
                    name="monto"
                    step="0.01"
                    min="0.01"
                    placeholder="Monto"
                    required
                    class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                >
                <input
                    type="text"
                    name="concepto"
                    placeholder="Concepto (ej: retiro para cambio)"
                    maxlength="255"
                    required
                    class="sm:col-span-1 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                >
                <button
                    type="submit"
                    class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-2 text-sm font-medium"
                >
                    Registrar
                </button>
            </form>

            @if ($movimientos->isNotEmpty())
                <table class="w-full text-sm border-collapse mt-4">
                    <thead>
                        <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                            <th class="py-2 pr-4">Hora</th>
                            <th class="py-2 pr-4">Tipo</th>
                            <th class="py-2 pr-4">Concepto</th>
                            <th class="py-2 pr-4">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movimientos as $movimiento)
                            <tr class="border-b border-[#19140035] dark:border-[#3E3E3A]">
                                <td class="py-2 pr-4">{{ $movimiento->created_at->format('H:i') }}</td>
                                <td class="py-2 pr-4 capitalize">{{ $movimiento->tipo }}</td>
                                <td class="py-2 pr-4">{{ $movimiento->concepto }}</td>
                                <td class="py-2 pr-4 {{ $movimiento->tipo === \App\Models\MovimientoCaja::TIPO_EGRESO ? 'text-[#F53003] dark:text-[#FF4433]' : 'text-[#0a7d1e] dark:text-[#44FF66]' }}">
                                    {{ $movimiento->tipo === \App\Models\MovimientoCaja::TIPO_EGRESO ? '-' : '+' }}{{ number_format((float) $movimiento->monto, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Cierre: irreversible en la práctica (no hay "reabrir caja"), por
             eso pasa por confirmación — mismo patrón que anular una
             venta/pago. --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Cerrar caja</h2>
            <form id="cierre-form" method="POST" action="{{ route('caja.cerrar') }}" class="space-y-3 max-w-sm">
                @csrf
                <div>
                    <label for="efectivo_contado" class="block text-sm font-medium mb-1">Efectivo contado</label>
                    <input
                        type="number"
                        id="efectivo_contado"
                        name="efectivo_contado"
                        step="0.01"
                        min="0"
                        required
                        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                    >
                </div>
                <div>
                    <label for="observaciones" class="block text-sm font-medium mb-1">Observaciones (opcional)</label>
                    <input
                        type="text"
                        id="observaciones"
                        name="observaciones"
                        maxlength="255"
                        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
                    >
                </div>
                <button
                    type="button"
                    id="abrir-confirmar-cierre"
                    class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
                >
                    Cerrar caja
                </button>
            </form>
        </div>

        <x-confirm-dialog
            id="confirmar-cierre-dialog"
            titulo="¿Cerrar caja?"
            confirmar-label="Sí, cerrar caja"
            cancelar-label="Seguir operando"
        >
            Efectivo esperado: <strong>${{ number_format($efectivoEsperado, 2) }}</strong>.
            Esta acción termina el turno actual — no se puede deshacer ni volver a abrirlo.
        </x-confirm-dialog>

        <script>
            (function () {
                const form = document.getElementById('cierre-form');
                const abrirBtn = document.getElementById('abrir-confirmar-cierre');
                const dialog = document.getElementById('confirmar-cierre-dialog');
                const confirmarBtn = document.getElementById('confirmar-cierre-dialog-confirmar');
                const cancelarBtn = document.getElementById('confirmar-cierre-dialog-cancelar');

                abrirBtn.addEventListener('click', () => {
                    if (!form.reportValidity()) {
                        return;
                    }
                    dialog.showModal();
                });

                confirmarBtn.addEventListener('click', () => {
                    dialog.close();
                    form.submit();
                });

                cancelarBtn.addEventListener('click', () => dialog.close());
            })();
        </script>
    @endif

    {{-- Historial de solo lectura — últimos 10 turnos cerrados. --}}
    @if ($historial->isNotEmpty())
        <h2 class="text-sm font-medium mt-8 mb-3">Historial reciente</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <th class="py-2 pr-4">Apertura</th>
                        <th class="py-2 pr-4">Cierre</th>
                        <th class="py-2 pr-4">Esperado</th>
                        <th class="py-2 pr-4">Contado</th>
                        <th class="py-2 pr-4">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($historial as $turno)
                        @php $diferencia = (float) $turno->diferencia; @endphp
                        <tr class="border-b border-[#19140035] dark:border-[#3E3E3A]">
                            <td class="py-2 pr-4">{{ $turno->abierta_en->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4">{{ $turno->cerrada_en->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4">{{ number_format((float) $turno->efectivo_esperado, 2) }}</td>
                            <td class="py-2 pr-4">{{ number_format((float) $turno->efectivo_contado, 2) }}</td>
                            <td class="py-2 pr-4 {{ $diferencia === 0.0 ? '' : ($diferencia < 0 ? 'text-[#F53003] dark:text-[#FF4433]' : 'text-[#0a7d1e] dark:text-[#44FF66]') }} font-medium">
                                {{ $diferencia > 0 ? '+' : '' }}{{ number_format($diferencia, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
