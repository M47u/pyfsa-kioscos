<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReporteController extends Controller
{
    /**
     * Nombres de día en español, indexados por Carbon::dayOfWeekIso
     * (1 = lunes ... 7 = domingo). La app no usa el sistema de traducciones
     * de Laravel para esto (locale en config/app.php sigue en 'en') — mismo
     * criterio que el resto de las vistas, que hardcodean el texto en
     * español directamente.
     */
    private const NOMBRES_DIA = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * Tramos del mes por rango de DÍA DEL MES (no semana ISO — ver
     * tendenciaPorTramoDelMes()). El tramo 3 llega hasta 31 aunque el mes
     * tenga menos días: DAY() nunca devuelve un valor que no haya existido
     * realmente en ese mes, así que no hace falta ajustar el límite por
     * mes (28/29/30/31).
     */
    private const TRAMOS = [
        1 => ['desde' => 1, 'hasta' => 10, 'label' => 'Día 1 al 10'],
        2 => ['desde' => 11, 'hasta' => 20, 'label' => 'Día 11 al 20'],
        3 => ['desde' => 21, 'hasta' => 31, 'label' => 'Día 21 a fin de mes'],
    ];

    /**
     * Arma las 4 secciones del módulo de Reportes (módulo 3.4, priorización
     * del usuario — ver documento de alcance). A propósito quedan afuera el
     * reporte de "productos por valor de stock invertido" (deferred a una
     * ronda futura) y cualquier filtro de rango de fechas: esto es un
     * primer corte fijo (hoy / semana actual).
     */
    public function index(): View
    {
        $inicioSemana = now()->startOfWeek();
        $finSemana = now()->endOfWeek();

        [$ventasPorDia, $totalHoy, $totalSemana, $diaPico, $cantidadTicketsHoy, $cantidadTicketsSemana, $resumenPorMedioPagoSemana] =
            $this->ventasDeLaSemana($inicioSemana, $finSemana);

        [$productoMasVendido, $cantidadMasVendida] = $this->productoMasVendidoDeLaSemana($inicioSemana, $finSemana);

        $totalPorCobrar = $this->totalPorCobrar();
        $rankingDeudores = $this->rankingDeudores();

        $productosBajoMinimo = $this->cantidadProductosBajoMinimo();

        $ventasOfflineConStockInsuficiente = $this->cantidadVentasOfflineConStockInsuficiente();

        $tendenciaPorTramo = $this->tendenciaPorTramoDelMes();

        [$topFinDeSemana, $topDiasDeSemana] = $this->masVendidoFinDeSemana();

        $ticketPromedioSemana = $cantidadTicketsSemana > 0 ? $totalSemana / $cantidadTicketsSemana : 0.0;

        $cantidadDeudoresQueSuperanLimite = $rankingDeudores->where('supera_limite', true)->count();
        $diferenciaUltimoCierre = $this->diferenciaUltimoCierreDeCaja();

        return view('reportes.index', [
            'ventasPorDia' => $ventasPorDia,
            'totalHoy' => $totalHoy,
            'totalSemana' => $totalSemana,
            'diaPico' => $diaPico,
            'cantidadTicketsHoy' => $cantidadTicketsHoy,
            'cantidadTicketsSemana' => $cantidadTicketsSemana,
            'ticketPromedioSemana' => $ticketPromedioSemana,
            'resumenPorMedioPagoSemana' => $resumenPorMedioPagoSemana,
            'productoMasVendido' => $productoMasVendido,
            'cantidadMasVendida' => $cantidadMasVendida,
            'totalPorCobrar' => $totalPorCobrar,
            'rankingDeudores' => $rankingDeudores,
            'productosBajoMinimo' => $productosBajoMinimo,
            'ventasOfflineConStockInsuficiente' => $ventasOfflineConStockInsuficiente,
            'cantidadDeudoresQueSuperanLimite' => $cantidadDeudoresQueSuperanLimite,
            'diferenciaUltimoCierre' => $diferenciaUltimoCierre,
            'tendenciaPorTramo' => $tendenciaPorTramo,
            'topFinDeSemana' => $topFinDeSemana,
            'topDiasDeSemana' => $topDiasDeSemana,
        ]);
    }

    /**
     * Trae TODAS las ventas de la semana actual (lunes a domingo, según
     * Carbon::startOfWeek()/endOfWeek() con el huso horario ya aplicado por
     * InitializeTenancyByAuthenticatedUser — ver CLAUDE.md) en una sola
     * query, y agrupa/suma en PHP por día. Nada de N+1: una query trae
     * todas las filas de la semana, el resto es agregación en memoria sobre
     * una colección ya chica (a lo sumo unas pocas decenas/cientos de
     * ventas por semana para este tipo de negocio).
     *
     * @return array{0: Collection<string, array{fecha: Carbon, nombre: string, total: float}>, 1: float, 2: float, 3: ?string, 4: int, 5: int, 6: array<string, float>}
     */
    private function ventasDeLaSemana(Carbon $inicioSemana, Carbon $finSemana): array
    {
        // whereNull('anulada_en'): una venta anulada (ver Venta::anulada_en
        // / VentaController::anular) no cuenta como venta real en ningún
        // reporte — ver el mismo filtro repetido en el resto de este
        // controller.
        $ventas = Venta::query()
            ->whereBetween('created_at', [$inicioSemana, $finSemana])
            ->whereNull('anulada_en')
            ->get(['total', 'created_at', 'medio_pago']);

        $totalesPorFecha = $ventas
            ->groupBy(fn (Venta $venta) => $venta->created_at->format('Y-m-d'))
            ->map(fn ($ventasDelDia) => (float) $ventasDelDia->sum('total'));

        // Se arman los 7 días de la semana (con 0 los que no tuvieron
        // ventas) para poder mostrar la semana completa en la vista y
        // determinar el día pico incluso si hoy es lunes.
        $ventasPorDia = collect(range(0, 6))->mapWithKeys(function (int $offset) use ($inicioSemana, $totalesPorFecha) {
            $fecha = $inicioSemana->copy()->addDays($offset);
            $clave = $fecha->format('Y-m-d');

            return [$clave => [
                'fecha' => $fecha,
                'nombre' => self::NOMBRES_DIA[$fecha->dayOfWeekIso],
                'total' => $totalesPorFecha->get($clave, 0.0),
            ]];
        });

        $totalSemana = $ventasPorDia->sum('total');
        $totalHoy = ($ventasPorDia->get(today()->format('Y-m-d')) ?? ['total' => 0.0])['total'];

        $diaPico = $ventasPorDia->sum('total') > 0
            ? $ventasPorDia->sortByDesc('total')->first()['nombre']
            : null;

        // Dashboard (POS/UX, gap encontrado por el usuario): cantidad de
        // tickets y ticket promedio — cada fila de $ventas YA es "hoy" o
        // "esta semana" (la query las trajo así), separar hoy es un filtro
        // en memoria sobre una colección ya chica, no una query nueva.
        $cantidadTicketsSemana = $ventas->count();
        $cantidadTicketsHoy = $ventas->filter(fn (Venta $venta) => $venta->created_at->isToday())->count();

        // Resumen por método de pago (semana): agrupado en PHP sobre la
        // misma colección ya traída — evita una segunda query solo para
        // esto. Se incluyen los 5 medios aunque no tengan ventas (0), para
        // que la vista no tenga que resolver cuáles faltan.
        $resumenPorMedioPagoSemana = collect(Venta::ETIQUETAS_MEDIO_PAGO)
            ->keys()
            ->mapWithKeys(fn (string $medio) => [
                $medio => (float) $ventas->where('medio_pago', $medio)->sum('total'),
            ])
            ->all();

        return [$ventasPorDia, $totalHoy, $totalSemana, $diaPico, $cantidadTicketsHoy, $cantidadTicketsSemana, $resumenPorMedioPagoSemana];
    }

    /**
     * Diferencia del último cierre de caja (ver Caja::diferencia) — null si
     * todavía no se cerró ninguna caja. Usado por la sección de Alertas:
     * una diferencia distinta de cero es justo el tipo de cosa que un
     * dueño quiere ver apenas entra a Reportes, no descubrir buceando en
     * /caja.
     */
    private function diferenciaUltimoCierreDeCaja(): ?float
    {
        $ultimaCaja = Caja::whereNotNull('cerrada_en')->latest('cerrada_en')->first();

        return $ultimaCaja !== null ? (float) $ultimaCaja->diferencia : null;
    }

    /**
     * "Más vendido" = mayor CANTIDAD vendida, no mayor facturación (una
     * decisión explícita del usuario: un producto barato que se vende
     * mucho puede ganarle en cantidad a uno caro que se vende poco, y acá
     * interesa lo primero). Se agrega con un join+groupBy a nivel de SQL
     * (una sola query) y recién con el producto_id ganador se busca el
     * Producto (otra query, pero una sola fila) — no hay N+1 porque no se
     * itera todo el catálogo ni todas las ventas en PHP.
     *
     * @return array{0: ?Producto, 1: int}
     */
    private function productoMasVendidoDeLaSemana(Carbon $inicioSemana, Carbon $finSemana): array
    {
        $fila = DB::table('items_venta')
            ->join('ventas', 'ventas.id', '=', 'items_venta.venta_id')
            ->whereBetween('ventas.created_at', [$inicioSemana, $finSemana])
            ->whereNull('ventas.anulada_en')
            ->selectRaw('items_venta.producto_id, SUM(items_venta.cantidad) as cantidad_total')
            ->groupBy('items_venta.producto_id')
            ->orderByDesc('cantidad_total')
            ->first();

        if ($fila === null) {
            return [null, 0];
        }

        return [Producto::find($fila->producto_id), (int) $fila->cantidad_total];
    }

    /**
     * Total adeudado por TODOS los clientes combinados: dos sumas
     * agregadas a nivel de SQL (una de ventas fiadas, una de pagos), no una
     * suma de Cliente::saldo() por cliente en un loop.
     */
    private function totalPorCobrar(): float
    {
        $totalFiado = (float) Venta::where('medio_pago', Venta::MEDIO_PAGO_FIADO)
            ->whereNull('anulada_en')
            ->sum('total');
        $totalPagado = (float) Pago::whereNull('anulado_en')->sum('monto');

        return $totalFiado - $totalPagado;
    }

    /**
     * Ranking de clientes deudores: una sola query con dos subagregados
     * (withSum) trae, por cliente, el total fiado y el total pagado ya
     * sumados por SQL. El saldo (resta de esos dos totales) y el flag de
     * "supera límite" se calculan en PHP sobre la colección ya traída, sin
     * volver a pegarle a la base por cliente (evita el N+1 de llamar
     * Cliente::saldo()/superaLimite() dentro de un loop).
     *
     * @return Collection<int, array{cliente: Cliente, saldo: float, supera_limite: bool}>
     */
    private function rankingDeudores(): Collection
    {
        return Cliente::query()
            ->withSum(['ventas as total_fiado' => fn ($query) => $query->where('medio_pago', Venta::MEDIO_PAGO_FIADO)->whereNull('anulada_en')], 'total')
            ->withSum(['pagos as total_pagos' => fn ($query) => $query->whereNull('anulado_en')], 'monto')
            ->get()
            ->map(function (Cliente $cliente) {
                $saldo = (float) ($cliente->total_fiado ?? 0) - (float) ($cliente->total_pagos ?? 0);

                return [
                    'cliente' => $cliente,
                    'saldo' => $saldo,
                    'supera_limite' => $saldo > (float) $cliente->limite_credito,
                ];
            })
            ->sortByDesc('saldo')
            ->values()
            ->take(10);
    }

    /**
     * Solo el conteo (no la lista completa: la vista de Productos ya
     * resalta visualmente los productos bajo mínimo, no hace falta
     * duplicar esa tabla acá). Una sola query con withSum trae el stock
     * calculado de todos los productos, el filtro corre en memoria sobre
     * la colección ya traída — mismo criterio que Producto::stockActual()
     * pero sin llamarlo por producto dentro de un loop.
     */
    private function cantidadProductosBajoMinimo(): int
    {
        return Producto::withSum('movimientos', 'cantidad')
            ->where('controla_stock', true)
            ->get()
            ->filter(fn (Producto $producto) => ($producto->movimientos_sum_cantidad ?? 0) < $producto->stock_minimo)
            ->count();
    }

    /**
     * Offline (documento de alcance — ver CLAUDE.md, arquitectura offline):
     * conteo de ventas que llegaron por la cola offline dejando stock
     * negativo (ver Venta::sincronizada_con_stock_insuficiente y
     * VentaController::store) — pendientes de revisar por el dueño. Mismo
     * criterio que cantidadProductosBajoMinimo(): solo el número, el link
     * "Ver ventas" en la vista lleva al listado completo filtrado
     * (ventas.index?stock_insuficiente=1) en vez de duplicar la tabla acá.
     * whereNull('anulada_en') porque una venta ya anulada no es algo
     * "pendiente de revisar" — su stock ya fue revertido.
     */
    private function cantidadVentasOfflineConStockInsuficiente(): int
    {
        return Venta::where('sincronizada_con_stock_insuficiente', true)
            ->whereNull('anulada_en')
            ->count();
    }

    /**
     * Tendencia de ventas por tramo del mes (1-10, 11-20, 21-fin de mes),
     * NO por semana ISO. Hipótesis validada con el usuario: el poder
     * adquisitivo de los clientes varía dentro del mes según el ciclo de
     * cobro de sueldo (fin de mes / quincena, patrón común en Argentina y
     * Paraguay), y ese ciclo se repite por DÍA DEL MES — una semana
     * calendario NO coincide con el ciclo de pago (el día 1 de un mes
     * puede caer cualquier día de la semana).
     *
     * Histórico completo (TODAS las ventas de siempre, no solo el mes
     * actual): el objetivo es ver el patrón acumulado a través de varios
     * meses. Con pocas semanas de uso real el ranking por tramo puede
     * salir ruidoso — no es un defecto del reporte, es una limitación
     * estadística real de tener poco volumen; mejora sola a medida que se
     * acumulan más meses de datos.
     *
     * Una sola query agrega total y total_fiado de los 3 tramos con un
     * CASE WHEN en SQL (no 3 queries sueltas). El top 5 de productos por
     * tramo SÍ necesita una query por tramo — "top N por grupo" no se
     * puede resolver de forma portable en una sola query — cada una
     * agregando en SQL con GROUP BY + ORDER BY + LIMIT vía
     * topProductosPorCantidad() (mismo patrón que
     * productoMasVendidoDeLaSemana()). Los productos ganadores de los 3
     * tramos se traen en una sola query con whereIn, no uno por uno.
     *
     * @return Collection<int, array{desde: int, hasta: int, label: string, total: float, total_fiado: float, pct_fiado: float, productos: Collection<int, array{producto: ?Producto, cantidad: int}>}>
     */
    private function tendenciaPorTramoDelMes(): Collection
    {
        $totalesPorTramo = DB::table('ventas')
            ->whereNull('anulada_en')
            ->selectRaw('
                CASE WHEN DAY(created_at) <= 10 THEN 1 WHEN DAY(created_at) <= 20 THEN 2 ELSE 3 END as tramo,
                SUM(total) as total,
                SUM(CASE WHEN medio_pago = ? THEN total ELSE 0 END) as total_fiado
            ', [Venta::MEDIO_PAGO_FIADO])
            ->groupByRaw('CASE WHEN DAY(created_at) <= 10 THEN 1 WHEN DAY(created_at) <= 20 THEN 2 ELSE 3 END')
            ->get()
            ->keyBy('tramo');

        $topPorTramo = collect(self::TRAMOS)->map(
            fn (array $rango) => $this->topProductosPorCantidad(
                fn ($query) => $query->whereRaw('DAY(ventas.created_at) BETWEEN ? AND ?', [$rango['desde'], $rango['hasta']])
            )
        );

        $idsProductos = $topPorTramo->flatten(1)->pluck('producto_id')->unique();
        $productos = Producto::whereIn('id', $idsProductos)->get()->keyBy('id');

        return collect(self::TRAMOS)->map(function (array $rango, int $tramo) use ($totalesPorTramo, $topPorTramo, $productos) {
            $fila = $totalesPorTramo->get($tramo);
            $total = (float) ($fila->total ?? 0);
            $totalFiado = (float) ($fila->total_fiado ?? 0);

            return [
                'desde' => $rango['desde'],
                'hasta' => $rango['hasta'],
                'label' => $rango['label'],
                'total' => $total,
                'total_fiado' => $totalFiado,
                'pct_fiado' => $total > 0 ? ($totalFiado / $total) * 100 : 0.0,
                'productos' => $topPorTramo->get($tramo, collect())->map(fn ($fila) => [
                    'producto' => $productos->get($fila->producto_id),
                    'cantidad' => (int) $fila->cantidad_total,
                ]),
            ];
        });
    }

    /**
     * Top 5 (por defecto) productos por CANTIDAD vendida (no facturación),
     * con un filtro adicional inyectado vía callback sobre el query
     * builder (rango de día del mes, fin de semana vs. resto, etc.).
     * Reutiliza el mismo patrón de join items_venta+ventas con
     * GROUP BY + ORDER BY + LIMIT a nivel SQL que
     * productoMasVendidoDeLaSemana() — nunca carga items_venta completo a
     * PHP.
     *
     * whereNull('ventas.anulada_en') acá en la base compartida: cubre a
     * los dos call sites (tendenciaPorTramoDelMes() y
     * masVendidoFinDeSemana()) sin repetirlo en cada uno.
     *
     * @return Collection<int, object{producto_id: int, cantidad_total: int}>
     */
    private function topProductosPorCantidad(\Closure $filtro, int $limite = 5): Collection
    {
        $query = DB::table('items_venta')
            ->join('ventas', 'ventas.id', '=', 'items_venta.venta_id')
            ->whereNull('ventas.anulada_en')
            ->selectRaw('items_venta.producto_id, SUM(items_venta.cantidad) as cantidad_total')
            ->groupBy('items_venta.producto_id')
            ->orderByDesc('cantidad_total')
            ->limit($limite);

        $filtro($query);

        return $query->get();
    }

    /**
     * Top 5 productos por cantidad, fin de semana (sábado + domingo) vs.
     * resto de la semana (lunes a viernes). Histórico completo, mismo
     * criterio de "mejora con más historial" que
     * tendenciaPorTramoDelMes().
     *
     * Usa DAYOFWEEK() de MySQL, que numera 1=domingo ... 7=sábado — OJO
     * que es una convención DISTINTA de Carbon::dayOfWeekIso (1=lunes ...
     * 7=domingo), que es la que se usa en el resto de este controller (ver
     * NOMBRES_DIA y ventasDeLaSemana()). No mezclar las dos.
     *
     * @return array{0: Collection<int, array{producto: ?Producto, cantidad: int}>, 1: Collection<int, array{producto: ?Producto, cantidad: int}>}
     */
    private function masVendidoFinDeSemana(): array
    {
        $topFinDeSemana = $this->topProductosPorCantidad(
            fn ($query) => $query->whereRaw('DAYOFWEEK(ventas.created_at) IN (1, 7)')
        );

        $topDiasDeSemana = $this->topProductosPorCantidad(
            fn ($query) => $query->whereRaw('DAYOFWEEK(ventas.created_at) NOT IN (1, 7)')
        );

        $idsProductos = $topFinDeSemana->pluck('producto_id')
            ->merge($topDiasDeSemana->pluck('producto_id'))
            ->unique();
        $productos = Producto::whereIn('id', $idsProductos)->get()->keyBy('id');

        $mapear = fn (Collection $filas) => $filas->map(fn ($fila) => [
            'producto' => $productos->get($fila->producto_id),
            'cantidad' => (int) $fila->cantidad_total,
        ]);

        return [$mapear($topFinDeSemana), $mapear($topDiasDeSemana)];
    }
}
