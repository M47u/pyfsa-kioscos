<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AbrirCajaRequest;
use App\Http\Requests\CerrarCajaRequest;
use App\Http\Requests\MovimientoCajaRequest;
use App\Models\Caja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Módulo de Caja (gap encontrado por el usuario, no está en el documento de
 * alcance original — spec de venta rápida/POS, sección "Caja"): apertura de
 * turno, ingresos/egresos manuales y cierre con diferencia. Compartido
 * dueño+empleado (a diferencia de Productos/Reportes/Usuarios): operar la
 * caja es parte de vender, no de administrar el comercio — ver el
 * sub-grupo de routes/tenant.php donde NO está anidado.
 */
class CajaController extends Controller
{
    /**
     * Estado actual: si hay una caja abierta, el panel de operación
     * (resumen por medio de pago, movimientos, formularios de
     * movimiento/cierre); si no, el formulario de apertura. Más las
     * últimas 10 cajas cerradas como historial de solo lectura.
     */
    public function show(): View
    {
        $cajaAbierta = $this->cajaAbierta();

        return view('caja.show', [
            'caja' => $cajaAbierta,
            'ventasPorMedioPago' => $cajaAbierta?->ventasPorMedioPago() ?? [],
            'totalPagos' => $cajaAbierta?->totalPagosDelPeriodo() ?? 0.0,
            'totalIngresos' => $cajaAbierta?->totalIngresos() ?? 0.0,
            'totalEgresos' => $cajaAbierta?->totalEgresos() ?? 0.0,
            'efectivoEsperado' => $cajaAbierta?->efectivoEsperadoActual() ?? 0.0,
            'movimientos' => $cajaAbierta?->movimientos()->latest()->get() ?? collect(),
            'historial' => Caja::query()->whereNotNull('cerrada_en')->latest('cerrada_en')->limit(10)->get(),
        ]);
    }

    /**
     * Solo puede haber una caja abierta a la vez (ver el comentario en la
     * migración de `cajas` sobre por qué esto se valida acá y no con una
     * constraint de base).
     */
    public function abrir(AbrirCajaRequest $request): RedirectResponse
    {
        if ($this->cajaAbierta() !== null) {
            throw ValidationException::withMessages([
                'monto_apertura' => 'Ya hay una caja abierta.',
            ]);
        }

        Caja::create([
            'abierta_en' => now(),
            'monto_apertura' => $request->validated('monto_apertura'),
            'user_id_apertura' => auth()->id(),
        ]);

        return redirect()->route('caja.show')->with('status', 'Caja abierta correctamente.');
    }

    public function registrarMovimiento(MovimientoCajaRequest $request): RedirectResponse
    {
        $caja = $this->cajaAbierta();

        abort_if($caja === null, 404, 'No hay una caja abierta.');

        $caja->movimientos()->create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('caja.show')->with('status', 'Movimiento registrado correctamente.');
    }

    /**
     * El efectivo esperado y la diferencia se calculan y CONGELAN acá — no
     * se recalculan después, aunque una venta se anule más tarde: son la
     * foto real de lo que se contó en ese momento, es lo que tiene sentido
     * auditar (mismo criterio de "nunca se edita, se registra" que el
     * resto del sistema).
     */
    public function cerrar(CerrarCajaRequest $request): RedirectResponse
    {
        $caja = $this->cajaAbierta();

        abort_if($caja === null, 404, 'No hay una caja abierta.');

        $efectivoEsperado = $caja->efectivoEsperadoActual();
        $efectivoContado = (float) $request->validated('efectivo_contado');

        $caja->update([
            'cerrada_en' => now(),
            'efectivo_esperado' => $efectivoEsperado,
            'efectivo_contado' => $efectivoContado,
            'diferencia' => $efectivoContado - $efectivoEsperado,
            'user_id_cierre' => auth()->id(),
            'observaciones' => $request->validated('observaciones'),
        ]);

        return redirect()->route('caja.show')->with('status', 'Caja cerrada correctamente.');
    }

    private function cajaAbierta(): ?Caja
    {
        return Caja::whereNull('cerrada_en')->latest('abierta_en')->first();
    }
}
