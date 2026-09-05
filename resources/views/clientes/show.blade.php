<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - {{ $cliente->nombre }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen p-6">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-lg font-medium">{{ $cliente->nombre }}</h1>
            <a href="{{ route('clientes.index') }}" class="text-sm underline">Volver a clientes</a>
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
             productos/index.blade.php. --}}
        <div class="mb-6 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Registrar pago</h2>
            <form method="POST" action="{{ route('clientes.pagos.store', $cliente) }}" class="flex gap-2">
                @csrf
                <input
                    type="number"
                    name="monto"
                    step="0.01"
                    min="0.01"
                    placeholder="Monto"
                    required
                    class="flex-1 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
                >
                <button
                    type="submit"
                    class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-2 text-sm font-medium"
                >
                    Registrar pago
                </button>
            </form>
        </div>

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
    </div>
</body>
</html>
