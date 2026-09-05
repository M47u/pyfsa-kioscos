@extends('layouts.app')

@section('title', $cliente->nombre)

@section('body-class', 'p-6')
@section('container-class', 'max-w-2xl mx-auto')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-lg font-medium">{{ $cliente->nombre }}</h1>
        <a href="{{ route('clientes.index') }}" class="text-sm underline">Volver a clientes</a>
    </div>

    <x-status-banner />
    <x-validation-errors />

    {{-- $saldo se calcula UNA sola vez y se pasa a superaLimite() en
         las dos veces que se usa más abajo, en vez de dejar que cada
         llamada recalcule saldo() desde cero (mismo bug ya arreglado
         en productos/index, ver Cliente::superaLimite()). --}}
    @php
        $saldo = $cliente->saldo();
        $superaLimite = $cliente->superaLimite($saldo);
    @endphp

    {{-- Datos del cliente + alerta visual si superó su límite de
         crédito (avisa, no bloquea — ver VentaController::store). --}}
    <div class="mb-6 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
        <dl class="grid grid-cols-2 gap-y-2 text-sm">
            <dt class="opacity-70">Teléfono</dt>
            <dd>{{ $cliente->telefono ?? '—' }}</dd>

            <dt class="opacity-70">Límite de crédito</dt>
            <dd>{{ number_format((float) $cliente->limite_credito, 2) }}</dd>

            <dt class="opacity-70">Saldo actual</dt>
            <dd class="{{ $superaLimite ? 'text-[#F53003] dark:text-[#FF4433] font-medium' : '' }}">
                {{ number_format($saldo, 2) }}
            </dd>
        </dl>

        @if ($superaLimite)
            <div class="mt-3 rounded-sm bg-[#fff2f2] dark:bg-[#1D0002] border border-[#F53003] text-[#F53003] dark:text-[#FF4433] px-3 py-2 text-sm">
                Este cliente superó su límite de crédito.
            </div>
        @endif
    </div>

    {{-- Registrar un pago nuevo, mismo patrón visual que "Reponer" en
         productos/index.blade.php. El botón ya no manda el form directo:
         abre el modal de confirmación de abajo, que muestra el monto
         tipeado antes de mandarlo — un pago mal tipeado (un 0 de más) no
         se puede deshacer con un "Volver atrás" del navegador. --}}
    <div class="mb-6 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
        <h2 class="text-sm font-medium mb-3">Registrar pago</h2>
        <form id="pago-form" method="POST" action="{{ route('clientes.pagos.store', $cliente) }}" class="flex gap-2">
            @csrf
            <input
                type="number"
                id="monto"
                name="monto"
                step="0.01"
                min="0.01"
                placeholder="Monto"
                required
                class="flex-1 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
            <button
                type="button"
                id="abrir-confirmar-pago"
                class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-2 text-sm font-medium"
            >
                Registrar pago
            </button>
        </form>
    </div>

    <x-confirm-dialog
        id="confirmar-pago-dialog"
        titulo="Confirmar pago"
        confirmar-label="Sí, registrar pago"
        cancelar-label="Cancelar"
    >
        Vas a registrar un pago de <strong>$<span id="monto-a-confirmar">0.00</span></strong>
        para <strong>{{ $cliente->nombre }}</strong>. Esta acción no se puede deshacer.
    </x-confirm-dialog>

    {{-- Historial cronológico: ventas fiadas y pagos mezclados (ver
         ClienteController::show), más reciente primero. --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                    <th class="py-2 pr-4">Fecha</th>
                    <th class="py-2 pr-4">Tipo</th>
                    <th class="py-2">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movimientos as $movimiento)
                    <tr class="border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <td class="py-2 pr-4">{{ $movimiento['fecha']->format('d/m/Y H:i') }}</td>
                        <td class="py-2 pr-4">
                            @if ($movimiento['tipo'] === 'venta')
                                Venta fiada
                            @else
                                Pago
                            @endif
                        </td>
                        <td class="py-2 {{ $movimiento['tipo'] === 'venta' ? 'text-[#F53003] dark:text-[#FF4433]' : 'text-[#0a7d1e] dark:text-[#44FF66]' }}">
                            {{ $movimiento['tipo'] === 'venta' ? '+' : '-' }}{{ number_format($movimiento['monto'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-4 text-center text-sm opacity-70">
                            Este cliente todavía no tiene movimientos de cuenta corriente.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        (function () {
            const pagoForm = document.getElementById('pago-form');
            const montoInput = document.getElementById('monto');
            const abrirConfirmarBtn = document.getElementById('abrir-confirmar-pago');
            const dialog = document.getElementById('confirmar-pago-dialog');
            const montoConfirmarSpan = document.getElementById('monto-a-confirmar');
            const confirmarBtn = document.getElementById('confirmar-pago-dialog-confirmar');
            const cancelarBtn = document.getElementById('confirmar-pago-dialog-cancelar');

            abrirConfirmarBtn.addEventListener('click', () => {
                // El botón ya no es type="submit", así que la validación
                // nativa del input (required, min, step) no dispara sola
                // al hacer click — la pedimos a mano antes de abrir el modal,
                // para no confirmar un monto vacío o inválido.
                if (!pagoForm.reportValidity()) {
                    return;
                }

                montoConfirmarSpan.textContent = parseFloat(montoInput.value).toFixed(2);
                dialog.showModal();
            });

            confirmarBtn.addEventListener('click', () => {
                dialog.close();
                pagoForm.submit();
            });

            cancelarBtn.addEventListener('click', () => dialog.close());
        })();
    </script>
@endsection
