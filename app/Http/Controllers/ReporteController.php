<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        [$ventasPorDia, $totalHoy, $totalSemana, $diaPico] = $this->ventasDeLaSemana($inicioSemana, $finSemana);

        [$productoMasVendido, $cantidadMasVendida] = $this->productoMasVendidoDeLaSemana($inicioSemana, $finSemana);

        $totalPorCobrar = $this->totalPorCobrar();
        $rankingDeudores = $this->rankingDeudores();

        $productosBajoMinimo = $this->cantidadProductosBajoMinimo();

        return view('reportes.index', [
            'ventasPorDia' => $ventasPorDia,
            'totalHoy' => $totalHoy,
            'totalSemana' => $totalSemana,
            'diaPico' => $diaPico,
            'productoMasVendido' => $productoMasVendido,
            'cantidadMasVendida' => $cantidadMasVendida,
            'totalPorCobrar' => $totalPorCobrar,
            'rankingDeudores' => $rankingDeudores,
            'productosBajoMinimo' => $productosBajoMinimo,
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
     * @return array{0: Collection<string, array{fecha: Carbon, nombre: string, total: float}>, 1: float, 2: float, 3: ?string}
     */
    private function ventasDeLaSemana(Carbon $inicioSemana, Carbon $finSemana): array
    {
        $ventas = Venta::query()
            ->whereBetween('created_at', [$inicioSemana, $finSemana])
            ->get(['total', 'created_at']);

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

        return [$ventasPorDia, $totalHoy, $totalSemana, $diaPico];
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
        $totalFiado = (float) Venta::where('medio_pago', Venta::MEDIO_PAGO_FIADO)->sum('total');
        $totalPagado = (float) Pago::sum('monto');

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
            ->withSum(['ventas as total_fiado' => fn ($query) => $query->where('medio_pago', Venta::MEDIO_PAGO_FIADO)], 'total')
            ->withSum('pagos as total_pagos', 'monto')
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
            ->get()
            ->filter(fn (Producto $producto) => ($producto->movimientos_sum_cantidad ?? 0) < $producto->stock_minimo)
            ->count();
    }
}
