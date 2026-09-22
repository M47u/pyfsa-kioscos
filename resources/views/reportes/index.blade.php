@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    <h1 class="text-lg font-medium mb-6">Reportes</h1>

    <x-status-banner />
    <x-validation-errors />

    {{-- Alertas (POS/UX, gap encontrado por el usuario): solo lo
         ACCIONABLE, reusando datos ya calculados más abajo — nada de
         notificaciones irrelevantes. Si no hay ninguna, no se muestra
         nada (evita ruido en el día a día sin problemas). --}}
    @php
        $hayAlertas = $productosBajoMinimo > 0
            || $cantidadDeudoresQueSuperanLimite > 0
            || $ventasOfflineConStockInsuficiente > 0
            || ($diferenciaUltimoCierre !== null && abs($diferenciaUltimoCierre) > 0.0);
    @endphp

    @if ($hayAlertas)
        <div class="mb-4 rounded-sm border border-[#F5A623] bg-[#fffbea] dark:bg-[#2a2200] text-[#8a6100] dark:text-[#F5C453] p-4">
            <h2 class="text-sm font-medium mb-2">Alertas</h2>
            <ul class="text-sm list-disc list-inside space-y-1">
                @if ($productosBajoMinimo > 0)
                    <li>
                        {{ $productosBajoMinimo }} {{ $productosBajoMinimo === 1 ? 'artículo bajo su stock mínimo' : 'artículos bajo su stock mínimo' }}
                        — <a href="{{ route('productos.index', ['bajo_minimo' => 1]) }}" class="underline">ver</a>
                    </li>
                @endif
                @if ($cantidadDeudoresQueSuperanLimite > 0)
                    <li>{{ $cantidadDeudoresQueSuperanLimite }} {{ $cantidadDeudoresQueSuperanLimite === 1 ? 'cliente superó' : 'clientes superaron' }} su límite de crédito</li>
                @endif
                @if ($ventasOfflineConStockInsuficiente > 0)
                    <li>
                        {{ $ventasOfflineConStockInsuficiente }} {{ $ventasOfflineConStockInsuficiente === 1 ? 'venta offline con stock insuficiente pendiente de revisar' : 'ventas offline con stock insuficiente pendientes de revisar' }}
                        — <a href="{{ route('ventas.index', ['stock_insuficiente' => 1]) }}" class="underline">ver</a>
                    </li>
                @endif
                @if ($diferenciaUltimoCierre !== null && abs($diferenciaUltimoCierre) > 0.0)
                    <li>
                        El último cierre de caja quedó con una diferencia de {{ number_format($diferenciaUltimoCierre, 2) }}
                        — <a href="{{ route('caja.show') }}" class="underline">ver caja</a>
                    </li>
                @endif
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        {{-- Ventas del día y de la semana, con el día pico destacado, más
             cantidad de tickets y ticket promedio (dashboard, gap
             encontrado por el usuario). --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Ventas del día y de la semana</h2>

            <dl class="grid grid-cols-2 gap-y-2 text-sm mb-4">
                <dt class="opacity-70">Hoy</dt>
                <dd class="font-medium">{{ number_format($totalHoy, 2) }} ({{ $cantidadTicketsHoy }} {{ $cantidadTicketsHoy === 1 ? 'ticket' : 'tickets' }})</dd>

                <dt class="opacity-70">Esta semana</dt>
                <dd class="font-medium">{{ number_format($totalSemana, 2) }} ({{ $cantidadTicketsSemana }} {{ $cantidadTicketsSemana === 1 ? 'ticket' : 'tickets' }})</dd>

                <dt class="opacity-70">Ticket promedio (semana)</dt>
                <dd class="font-medium">{{ number_format($ticketPromedioSemana, 2) }}</dd>
            </dl>

            <table class="w-full text-sm border-collapse">
                <tbody>
                    @foreach ($ventasPorDia as $dia)
                        <tr class="border-b border-[#19140035] dark:border-[#3E3E3A] last:border-0 {{ $dia['nombre'] === $diaPico ? 'bg-[#f5f5f4] dark:bg-[#161615] font-medium' : '' }}">
                            <td class="py-1.5 pr-4">
                                {{ $dia['nombre'] }}
                                @if ($dia['nombre'] === $diaPico)
                                    <span class="ml-1 text-xs">(día pico)</span>
                                @endif
                            </td>
                            <td class="py-1.5 text-right">{{ number_format($dia['total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Producto más vendido de la semana, por CANTIDAD (no por
             facturación) — ver ReporteController::productoMasVendidoDeLaSemana(). --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Artículo más vendido de la semana</h2>

            @if ($productoMasVendido !== null)
                <p class="text-base font-medium">{{ $productoMasVendido->nombre }}</p>
                <p class="text-sm opacity-70">{{ $cantidadMasVendida }} unidades vendidas esta semana</p>
            @else
                <p class="text-sm opacity-70">Todavía no hay ventas esta semana.</p>
            @endif
        </div>

        {{-- Resumen por método de pago (semana) — dashboard, gap
             encontrado por el usuario. --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Ventas de la semana por método de pago</h2>

            <dl class="grid grid-cols-2 gap-y-2 text-sm">
                @foreach (\App\Models\Venta::ETIQUETAS_MEDIO_PAGO as $medio => $etiqueta)
                    <dt class="opacity-70">{{ $etiqueta }}</dt>
                    <dd class="font-medium">{{ number_format($resumenPorMedioPagoSemana[$medio] ?? 0, 2) }}</dd>
                @endforeach
            </dl>
        </div>

        {{-- Cuentas por cobrar (fiado): total combinado + ranking de
             deudores. Diferenciador del producto, no estaba en el
             documento de alcance original. --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Cuentas por cobrar (fiado)</h2>

            <p class="text-sm opacity-70 mb-1">Total por cobrar</p>
            <p class="text-base font-medium mb-4">{{ number_format($totalPorCobrar, 2) }}</p>

            @if ($rankingDeudores->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                                <th class="py-1.5 pr-4">Cliente</th>
                                <th class="py-1.5">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rankingDeudores as $fila)
                                <tr class="border-b border-[#19140035] dark:border-[#3E3E3A] last:border-0 {{ $fila['supera_limite'] ? 'bg-[#fff2f2] dark:bg-[#1D0002]' : '' }}">
                                    <td class="py-1.5 pr-4">
                                        <a href="{{ route('clientes.show', $fila['cliente']) }}" class="underline">{{ $fila['cliente']->nombre }}</a>
                                    </td>
                                    <td class="py-1.5 {{ $fila['supera_limite'] ? 'text-[#F53003] dark:text-[#FF4433] font-medium' : '' }}">
                                        {{ number_format($fila['saldo'], 2) }}
                                        @if ($fila['supera_limite'])
                                            <span class="ml-1 text-xs">(supera el límite)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm opacity-70">No hay clientes con saldo fiado todavía.</p>
            @endif
        </div>

        {{-- Solo el número: la vista de Productos ya resalta cada fila
             bajo mínimo, no hace falta duplicar la tabla acá. --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Stock bajo mínimo</h2>

            <p class="text-2xl font-medium {{ $productosBajoMinimo > 0 ? 'text-[#F53003] dark:text-[#FF4433]' : '' }}">
                {{ $productosBajoMinimo }}
            </p>
            <p class="text-sm opacity-70 mb-3">
                {{ $productosBajoMinimo === 1 ? 'artículo por debajo de su stock mínimo' : 'artículos por debajo de su stock mínimo' }}
            </p>

            <a href="{{ route('productos.index', ['bajo_minimo' => 1]) }}" class="underline text-sm">Ver artículos</a>
        </div>

        {{-- Offline (documento de alcance — ver CLAUDE.md, arquitectura
             offline): ventas que llegaron por la cola offline dejando stock
             negativo (ver Venta::sincronizada_con_stock_insuficiente),
             pendientes de que el dueño las revise. Mismo criterio que "Stock
             bajo mínimo": solo el número acá, la tabla completa vive en
             ventas/index.blade.php. --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-3">Ventas offline con stock insuficiente</h2>

            <p class="text-2xl font-medium {{ $ventasOfflineConStockInsuficiente > 0 ? 'text-[#F53003] dark:text-[#FF4433]' : '' }}">
                {{ $ventasOfflineConStockInsuficiente }}
            </p>
            <p class="text-sm opacity-70 mb-3">
                {{ $ventasOfflineConStockInsuficiente === 1 ? 'venta sincronizada dejando stock negativo, pendiente de revisar' : 'ventas sincronizadas dejando stock negativo, pendientes de revisar' }}
            </p>

            <a href="{{ route('ventas.index', ['stock_insuficiente' => 1]) }}" class="underline text-sm">Ver ventas</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 mb-4">
        {{-- Tendencia por tramo del mes (1-10 / 11-20 / 21-fin de mes), NO
             semana ISO — el corte sigue el ciclo de cobro de sueldo (fin de
             mes / quincena), ver ReporteController::tendenciaPorTramoDelMes(). --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-1">Tendencia por tramo del mes</h2>
            <p class="text-xs opacity-50 mb-3">Histórico completo. Mejora a medida que se acumulan más meses de datos — con poco volumen el ranking puede salir ruidoso.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($tendenciaPorTramo as $tramo)
                    <div class="{{ !$loop->last ? 'md:border-r md:border-[#19140035] md:dark:border-[#3E3E3A] md:pr-4' : '' }}">
                        <h3 class="text-sm font-medium mb-2">{{ $tramo['label'] }}</h3>

                        <dl class="grid grid-cols-2 gap-y-1 text-sm mb-3">
                            <dt class="opacity-70">Total vendido</dt>
                            <dd class="font-medium text-right">{{ number_format($tramo['total'], 2) }}</dd>

                            <dt class="opacity-70">% fiado</dt>
                            <dd class="font-medium text-right">{{ number_format($tramo['pct_fiado'], 1) }}%</dd>
                        </dl>

                        @if ($tramo['productos']->isNotEmpty())
                            <p class="text-xs opacity-70 mb-1">Top 5 artículos (por cantidad)</p>
                            <ol class="text-sm list-decimal list-inside space-y-0.5">
                                @foreach ($tramo['productos'] as $fila)
                                    <li>
                                        {{ $fila['producto']?->nombre ?? 'Artículo eliminado' }}
                                        <span class="opacity-70">({{ $fila['cantidad'] }})</span>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <p class="text-sm opacity-70">Todavía no hay ventas en este tramo.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Más vendido en fin de semana vs. resto de la semana. Histórico
             completo — ver ReporteController::masVendidoFinDeSemana(). --}}
        <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] p-4">
            <h2 class="text-sm font-medium mb-1">Más vendido en fin de semana</h2>
            <p class="text-xs opacity-50 mb-3">Histórico completo. Mejora a medida que se acumulan más meses de datos.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:border-r md:border-[#19140035] md:dark:border-[#3E3E3A] md:pr-4">
                    <h3 class="text-sm font-medium mb-2">Fin de semana (sáb. y dom.)</h3>

                    @if ($topFinDeSemana->isNotEmpty())
                        <ol class="text-sm list-decimal list-inside space-y-0.5">
                            @foreach ($topFinDeSemana as $fila)
                                <li>
                                    {{ $fila['producto']?->nombre ?? 'Artículo eliminado' }}
                                    <span class="opacity-70">({{ $fila['cantidad'] }})</span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-sm opacity-70">Todavía no hay ventas de fin de semana.</p>
                    @endif
                </div>

                <div>
                    <h3 class="text-sm font-medium mb-2">Lunes a viernes</h3>

                    @if ($topDiasDeSemana->isNotEmpty())
                        <ol class="text-sm list-decimal list-inside space-y-0.5">
                            @foreach ($topDiasDeSemana as $fila)
                                <li>
                                    {{ $fila['producto']?->nombre ?? 'Artículo eliminado' }}
                                    <span class="opacity-70">({{ $fila['cantidad'] }})</span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-sm opacity-70">Todavía no hay ventas de lunes a viernes.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
