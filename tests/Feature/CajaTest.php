<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\User;
use App\Models\Venta;

/**
 * Módulo de Caja (gap encontrado por el usuario, no está en el documento de
 * alcance original): apertura de turno, ingresos/egresos manuales y cierre
 * con diferencia. Compartido dueño+empleado (ver RolTest para el gate GET
 * de /caja; acá se cubren las acciones POST, que el gate genérico no
 * ejercita).
 */
class CajaTest extends TenantTestCase
{
    public function test_abrir_caja_crea_turno_con_monto_inicial(): void
    {
        $response = $this->actingAs($this->user)->post(route('caja.abrir'), [
            'monto_apertura' => 1000,
        ]);

        $response->assertRedirect(route('caja.show'));
        $this->assertDatabaseHas('cajas', [
            'monto_apertura' => 1000,
            'user_id_apertura' => $this->user->id,
            'cerrada_en' => null,
        ]);
    }

    public function test_no_se_puede_abrir_una_segunda_caja_mientras_hay_una_abierta(): void
    {
        Caja::create(['abierta_en' => now(), 'monto_apertura' => 500, 'user_id_apertura' => $this->user->id]);

        $response = $this->actingAs($this->user)->post(route('caja.abrir'), [
            'monto_apertura' => 200,
        ]);

        $response->assertSessionHasErrors('monto_apertura');
        $this->assertSame(1, Caja::count());
    }

    public function test_registrar_movimiento_ingreso_y_egreso(): void
    {
        $caja = Caja::create(['abierta_en' => now(), 'monto_apertura' => 1000, 'user_id_apertura' => $this->user->id]);

        $this->actingAs($this->user)->post(route('caja.movimientos.store'), [
            'tipo' => MovimientoCaja::TIPO_INGRESO,
            'monto' => 300,
            'concepto' => 'Aporte extra',
        ])->assertRedirect(route('caja.show'));

        $this->actingAs($this->user)->post(route('caja.movimientos.store'), [
            'tipo' => MovimientoCaja::TIPO_EGRESO,
            'monto' => 150,
            'concepto' => 'Retiro para cambio',
        ])->assertRedirect(route('caja.show'));

        $this->assertSame(300.0, $caja->fresh()->totalIngresos());
        $this->assertSame(150.0, $caja->fresh()->totalEgresos());
    }

    public function test_registrar_movimiento_sin_caja_abierta_falla(): void
    {
        $response = $this->actingAs($this->user)->post(route('caja.movimientos.store'), [
            'tipo' => MovimientoCaja::TIPO_INGRESO,
            'monto' => 100,
            'concepto' => 'x',
        ]);

        $response->assertNotFound();
        $this->assertSame(0, MovimientoCaja::count());
    }

    /**
     * Escenario completo: apertura + venta en efectivo + venta a cuenta
     * (no debe sumar al efectivo esperado) + cobro de esa cuenta (SÍ suma,
     * ver la limitación documentada en Caja::totalPagosDelPeriodo) +
     * ingreso + egreso. El efectivo esperado y la diferencia se calculan y
     * se congelan al cerrar.
     */
    public function test_cerrar_caja_calcula_efectivo_esperado_y_diferencia(): void
    {
        $caja = Caja::create(['abierta_en' => now()->subHour(), 'monto_apertura' => 1000, 'user_id_apertura' => $this->user->id]);

        $cliente = Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => null, 'limite_credito' => 5000]);

        Venta::create(['user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_EFECTIVO, 'total' => 500]);
        Venta::create(['cliente_id' => $cliente->id, 'user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_FIADO, 'total' => 800]);
        Venta::create(['user_id' => $this->user->id, 'medio_pago' => Venta::MEDIO_PAGO_QR, 'total' => 300]);
        Pago::create(['cliente_id' => $cliente->id, 'monto' => 200, 'user_id' => $this->user->id]);

        $caja->movimientos()->create(['tipo' => MovimientoCaja::TIPO_INGRESO, 'monto' => 50, 'concepto' => 'Aporte', 'user_id' => $this->user->id]);
        $caja->movimientos()->create(['tipo' => MovimientoCaja::TIPO_EGRESO, 'monto' => 30, 'concepto' => 'Retiro', 'user_id' => $this->user->id]);

        // Esperado: 1000 (apertura) + 500 (venta efectivo) + 200 (cobro de
        // fiado) + 50 (ingreso) - 30 (egreso) = 1720. La venta fiada (800,
        // la deuda en sí) y la venta QR (300) NO entran al efectivo físico.
        $this->assertSame(1720.0, $caja->fresh()->efectivoEsperadoActual());

        $response = $this->actingAs($this->user)->post(route('caja.cerrar'), [
            'efectivo_contado' => 1700,
            'observaciones' => 'Faltaron 20',
        ]);

        $response->assertRedirect(route('caja.show'));

        $caja->refresh();
        $this->assertNotNull($caja->cerrada_en);
        $this->assertSame(1720.0, (float) $caja->efectivo_esperado);
        $this->assertSame(1700.0, (float) $caja->efectivo_contado);
        $this->assertSame(-20.0, (float) $caja->diferencia);
        $this->assertSame($this->user->id, $caja->user_id_cierre);
    }

    public function test_cerrar_caja_sin_caja_abierta_falla(): void
    {
        $response = $this->actingAs($this->user)->post(route('caja.cerrar'), [
            'efectivo_contado' => 100,
        ]);

        $response->assertNotFound();
    }

    /**
     * Compartida dueño+empleado (spec POS: "VENDEDOR: Venta, Caja") — a
     * diferencia de Productos/Reportes/Usuarios. RolTest ya cubre el GET;
     * acá se cubren las acciones POST reales (abrir/operar/cerrar), que un
     * empleado necesita para su turno de mostrador.
     */
    public function test_empleado_puede_abrir_operar_y_cerrar_caja(): void
    {
        $empleado = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_EMPLEADO,
        ]);

        $this->actingAs($empleado)->post(route('caja.abrir'), ['monto_apertura' => 500])
            ->assertRedirect(route('caja.show'));

        $this->actingAs($empleado)->post(route('caja.movimientos.store'), [
            'tipo' => MovimientoCaja::TIPO_INGRESO,
            'monto' => 100,
            'concepto' => 'Vuelto adicional',
        ])->assertRedirect(route('caja.show'));

        $this->actingAs($empleado)->post(route('caja.cerrar'), ['efectivo_contado' => 600])
            ->assertRedirect(route('caja.show'));

        $this->assertNotNull(Caja::first()->cerrada_en);
    }
}
