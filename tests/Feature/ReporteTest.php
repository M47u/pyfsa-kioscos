<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * La semana de referencia para todos estos tests está CONGELADA con
 * Carbon::setTestNow() en vez de usar "hoy" real: el reporte agrupa por
 * semana (lunes a domingo, ver ReporteController::ventasDeLaSemana()) y
 * necesitamos fechas de lunes/martes/etc. estables para poder armar el
 * escenario del "día pico" sin que el test sea sensible al día real en que
 * se corre la suite.
 *
 * 2026-06-08 es lunes, 2026-06-10 es miércoles (mitad de la semana y mitad
 * del día, lejos de cualquier borde de medianoche/huso horario).
 */
class ReporteTest extends TenantTestCase
{
    private const LUNES = '2026-06-08 10:00:00';

    private const MARTES = '2026-06-09 10:00:00';

    private const MIERCOLES = '2026-06-10 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::MIERCOLES));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearProducto(string $nombre, int $precioVenta, int $stockMinimo = 5): Producto
    {
        $producto = Producto::create([
            'nombre' => $nombre,
            'codigo_barras' => null,
            'precio_costo' => (int) ($precioVenta * 0.7),
            'precio_venta' => $precioVenta,
            'stock_minimo' => $stockMinimo,
        ]);

        $producto->movimientos()->create([
            'tipo' => MovimientoStock::TIPO_REPOSICION,
            'cantidad' => 1000,
            'user_id' => $this->user->id,
        ]);

        return $producto;
    }

    /**
     * Crea una venta fechada a mano (Venta::create() usaría "ahora" para
     * created_at; acá lo pisamos con una query directa después de crearla,
     * sin pasar por el evento de timestamps de Eloquent).
     */
    private function crearVentaEnFecha(string $fecha, int $total, ?Cliente $cliente = null, string $medioPago = Venta::MEDIO_PAGO_EFECTIVO): Venta
    {
        $venta = Venta::create([
            'cliente_id' => $cliente?->id,
            'user_id' => $this->user->id,
            'medio_pago' => $medioPago,
            'total' => $total,
        ]);

        Venta::where('id', $venta->id)->update(['created_at' => $fecha]);

        return $venta->fresh();
    }

    public function test_totales_de_hoy_y_de_la_semana_con_dia_pico(): void
    {
        $this->crearVentaEnFecha(self::LUNES, 500);
        $this->crearVentaEnFecha(self::MARTES, 5000); // día pico
        $this->crearVentaEnFecha(self::MIERCOLES, 700); // "hoy"

        // Fuera de la semana actual (semana anterior): no debe contarse.
        $this->crearVentaEnFecha('2026-06-01 10:00:00', 99999);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('totalHoy', 700.0);
        $response->assertViewHas('totalSemana', 6200.0);
        $response->assertViewHas('diaPico', 'Martes');
    }

    public function test_producto_mas_vendido_de_la_semana_es_por_cantidad_no_por_facturacion(): void
    {
        // A: 2 unidades a 5000 c/u = 10000 de facturación, pero solo 2 de cantidad.
        $productoA = $this->crearProducto('Notebook', 5000);
        // B: 50 unidades a 100 c/u = 5000 de facturación, pero 50 de cantidad (gana).
        $productoB = $this->crearProducto('Chicle', 100);

        $ventaA = $this->crearVentaEnFecha(self::MARTES, 10000);
        $ventaA->items()->create(['producto_id' => $productoA->id, 'cantidad' => 2, 'precio_unitario' => 5000]);

        $ventaB = $this->crearVentaEnFecha(self::MIERCOLES, 5000);
        $ventaB->items()->create(['producto_id' => $productoB->id, 'cantidad' => 50, 'precio_unitario' => 100]);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('productoMasVendido', fn (Producto $producto) => $producto->id === $productoB->id);
        $response->assertViewHas('cantidadMasVendida', 50);
    }

    public function test_cuentas_por_cobrar_total_y_ranking_de_deudores(): void
    {
        // A: saldo 3000, límite 2000 -> supera.
        $clienteA = Cliente::create(['nombre' => 'Cliente A', 'telefono' => null, 'limite_credito' => 2000]);
        $this->crearVentaEnFecha(self::LUNES, 5000, $clienteA, Venta::MEDIO_PAGO_FIADO);
        $clienteA->pagos()->create(['monto' => 2000, 'user_id' => $this->user->id]);

        // B: saldo 800, límite 5000 -> no supera.
        $clienteB = Cliente::create(['nombre' => 'Cliente B', 'telefono' => null, 'limite_credito' => 5000]);
        $this->crearVentaEnFecha(self::MARTES, 1000, $clienteB, Venta::MEDIO_PAGO_FIADO);
        $clienteB->pagos()->create(['monto' => 200, 'user_id' => $this->user->id]);

        // C: saldo 500, límite 100 -> supera, pero saldo menor que A y B.
        $clienteC = Cliente::create(['nombre' => 'Cliente C', 'telefono' => null, 'limite_credito' => 100]);
        $this->crearVentaEnFecha(self::MIERCOLES, 10000, $clienteC, Venta::MEDIO_PAGO_FIADO);
        $clienteC->pagos()->create(['monto' => 9500, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('totalPorCobrar', 4300.0);

        $response->assertViewHas('rankingDeudores', function ($ranking) use ($clienteA, $clienteB, $clienteC) {
            $ids = $ranking->pluck('cliente.id')->values()->all();

            return $ids === [$clienteA->id, $clienteB->id, $clienteC->id]
                && $ranking[0]['saldo'] === 3000.0 && $ranking[0]['supera_limite'] === true
                && $ranking[1]['saldo'] === 800.0 && $ranking[1]['supera_limite'] === false
                && $ranking[2]['saldo'] === 500.0 && $ranking[2]['supera_limite'] === true;
        });
    }

    public function test_resumen_de_stock_bajo_minimo_cuenta_solo_los_productos_por_debajo(): void
    {
        $sobreMinimo = $this->crearProducto('Con stock', 100, stockMinimo: 5);
        // El helper crearProducto ya repone 1000 de stock, por eso "sobre mínimo".

        $bajoMinimo1 = Producto::create([
            'nombre' => 'Bajo minimo 1', 'codigo_barras' => null,
            'precio_costo' => 50, 'precio_venta' => 100, 'stock_minimo' => 5,
        ]);
        $bajoMinimo1->movimientos()->create(['tipo' => MovimientoStock::TIPO_REPOSICION, 'cantidad' => 2, 'user_id' => $this->user->id]);

        $bajoMinimo2 = Producto::create([
            'nombre' => 'Bajo minimo 2', 'codigo_barras' => null,
            'precio_costo' => 50, 'precio_venta' => 100, 'stock_minimo' => 3,
        ]);
        // Sin movimientos: stock actual 0, bajo el mínimo de 3.

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('productosBajoMinimo', 2);
    }

    /**
     * Tramo del mes por rango de DÍA DEL MES (no semana ISO). Tres ventas
     * en tres días de mes distintos (5, 15, 25), cada una con un producto
     * distinto, deben caer cada una en su tramo (1-10, 11-20, 21-fin de
     * mes) con el producto correcto como top de ESE tramo. Es histórico
     * (no depende del "hoy" congelado), así que las fechas no necesitan
     * estar en la semana de referencia.
     */
    public function test_tendencia_por_tramo_del_mes_agrupa_por_dia_del_mes_y_calcula_top_de_cada_tramo(): void
    {
        $productoTramo1 = $this->crearProducto('Tramo 1', 100);
        $ventaTramo1 = $this->crearVentaEnFecha('2026-06-05 10:00:00', 700);
        $ventaTramo1->items()->create(['producto_id' => $productoTramo1->id, 'cantidad' => 7, 'precio_unitario' => 100]);

        $productoTramo2 = $this->crearProducto('Tramo 2', 100);
        $ventaTramo2 = $this->crearVentaEnFecha('2026-06-15 10:00:00', 800);
        $ventaTramo2->items()->create(['producto_id' => $productoTramo2->id, 'cantidad' => 8, 'precio_unitario' => 100]);

        $productoTramo3 = $this->crearProducto('Tramo 3', 100);
        $ventaTramo3 = $this->crearVentaEnFecha('2026-06-25 10:00:00', 900);
        $ventaTramo3->items()->create(['producto_id' => $productoTramo3->id, 'cantidad' => 9, 'precio_unitario' => 100]);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('tendenciaPorTramo', function ($tendencia) use ($productoTramo1, $productoTramo2, $productoTramo3) {
            return $tendencia[1]['total'] === 700.0
                && $tendencia[1]['productos']->first()['producto']->id === $productoTramo1->id
                && $tendencia[1]['productos']->first()['cantidad'] === 7
                && $tendencia[2]['total'] === 800.0
                && $tendencia[2]['productos']->first()['producto']->id === $productoTramo2->id
                && $tendencia[2]['productos']->first()['cantidad'] === 8
                && $tendencia[3]['total'] === 900.0
                && $tendencia[3]['productos']->first()['producto']->id === $productoTramo3->id
                && $tendencia[3]['productos']->first()['cantidad'] === 9;
        });
    }

    /**
     * % fiado por tramo: se calcula por MONTO (no por cantidad de ventas).
     * Una venta en efectivo de 3000 y una fiada de 1000 en el mismo tramo
     * (día 1-10) dan un total de 4000 con 25% fiado.
     */
    public function test_porcentaje_fiado_por_tramo_se_calcula_por_monto(): void
    {
        $this->crearVentaEnFecha('2026-06-03 10:00:00', 3000, medioPago: Venta::MEDIO_PAGO_EFECTIVO);
        $this->crearVentaEnFecha('2026-06-07 10:00:00', 1000, medioPago: Venta::MEDIO_PAGO_FIADO);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('tendenciaPorTramo', function ($tendencia) {
            return $tendencia[1]['total'] === 4000.0
                && $tendencia[1]['total_fiado'] === 1000.0
                && $tendencia[1]['pct_fiado'] === 25.0;
        });
    }

    /**
     * Fin de semana (sábado + domingo, DAYOFWEEK() 1 y 7) vs. resto de la
     * semana: el top 5 de cada lado se calcula sobre ventas separadas. Se
     * reutiliza LUNES (2026-06-08) como día de semana; 2026-06-06 es
     * sábado y 2026-06-07 es domingo de la misma semana.
     */
    public function test_mas_vendido_fin_de_semana_separa_sabado_y_domingo_del_resto_de_la_semana(): void
    {
        $productoFinde = $this->crearProducto('Producto finde', 100);

        $ventaSabado = $this->crearVentaEnFecha('2026-06-06 10:00:00', 1000);
        $ventaSabado->items()->create(['producto_id' => $productoFinde->id, 'cantidad' => 10, 'precio_unitario' => 100]);

        $ventaDomingo = $this->crearVentaEnFecha('2026-06-07 10:00:00', 500);
        $ventaDomingo->items()->create(['producto_id' => $productoFinde->id, 'cantidad' => 5, 'precio_unitario' => 100]);

        $productoSemana = $this->crearProducto('Producto semana', 100);
        $ventaLunes = $this->crearVentaEnFecha(self::LUNES, 2000);
        $ventaLunes->items()->create(['producto_id' => $productoSemana->id, 'cantidad' => 20, 'precio_unitario' => 100]);

        $response = $this->actingAs($this->user)->get(route('reportes.index'));

        $response->assertOk();
        $response->assertViewHas('topFinDeSemana', function ($top) use ($productoFinde) {
            return $top->first()['producto']->id === $productoFinde->id
                && $top->first()['cantidad'] === 15;
        });
        $response->assertViewHas('topDiasDeSemana', function ($top) use ($productoSemana) {
            return $top->first()['producto']->id === $productoSemana->id
                && $top->first()['cantidad'] === 20;
        });
    }
}
