<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un turno de caja completo: desde que se abre (ver CajaController::abrir())
 * hasta que se cierra (CajaController::cerrar()). Módulo de Caja (gap
 * encontrado por el usuario, no está en el documento de alcance original)
 * — pensado para operar un kiosco, no un sistema contable: una fila por
 * turno, nada de asientos ni cuentas contables.
 *
 * Solo puede haber UNA caja abierta a la vez (cerrada_en null) — ver el
 * comentario de la migración de `cajas` sobre por qué eso se valida en
 * CajaController y no con una constraint de base.
 */
class Caja extends Model
{
    protected $table = 'cajas';

    protected $fillable = [
        'abierta_en',
        'monto_apertura',
        'user_id_apertura',
        'cerrada_en',
        'efectivo_esperado',
        'efectivo_contado',
        'diferencia',
        'user_id_cierre',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'abierta_en' => 'datetime',
            'monto_apertura' => 'decimal:2',
            'cerrada_en' => 'datetime',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function estaAbierta(): bool
    {
        return $this->cerrada_en === null;
    }

    /**
     * Fin del período a considerar para los cálculos de abajo: "ahora" si
     * la caja sigue abierta (cálculo EN VIVO, ver CajaController::show()),
     * o el momento de cierre si ya se cerró (para poder recalcular/mostrar
     * el historial de una caja ya cerrada de forma consistente, ver
     * caja/show.blade.php).
     */
    private function finDelPeriodo(): Carbon
    {
        return $this->cerrada_en ?? now();
    }

    /**
     * Total vendido en el período de esta caja, agrupado por medio de pago
     * (ver Venta::ETIQUETAS_MEDIO_PAGO) — no anuladas. Las VENTAS no tienen
     * caja_id (ver el comentario de la migración de movimientos_caja): se
     * filtran por rango de fecha [abierta_en, finDelPeriodo()], que alcanza
     * porque solo hay una caja abierta a la vez (nunca se solapan).
     *
     * @return array<string, float>
     */
    public function ventasPorMedioPago(): array
    {
        $filas = Venta::query()
            ->whereBetween('created_at', [$this->abierta_en, $this->finDelPeriodo()])
            ->whereNull('anulada_en')
            ->selectRaw('medio_pago, SUM(total) as total')
            ->groupBy('medio_pago')
            ->pluck('total', 'medio_pago');

        return collect(Venta::ETIQUETAS_MEDIO_PAGO)
            ->keys()
            ->mapWithKeys(fn (string $medio) => [$medio => (float) ($filas[$medio] ?? 0)])
            ->all();
    }

    /**
     * Cobros de cuenta corriente (Pago) dentro del período. LIMITACIÓN
     * CONOCIDA Y DOCUMENTADA: `Pago` no distingue medio de pago (ver su
     * migración) — se asume que todo cobro de fiado entra como efectivo,
     * la práctica real más común en un kiosco. Si en el futuro se permite
     * cobrar fiado por transferencia/débito/QR, hay que agregar esa
     * columna y filtrar acá también (y en efectivoEsperadoActual()).
     */
    public function totalPagosDelPeriodo(): float
    {
        return (float) Pago::query()
            ->whereBetween('created_at', [$this->abierta_en, $this->finDelPeriodo()])
            ->whereNull('anulado_en')
            ->sum('monto');
    }

    public function totalIngresos(): float
    {
        return (float) $this->movimientos()->where('tipo', MovimientoCaja::TIPO_INGRESO)->sum('monto');
    }

    public function totalEgresos(): float
    {
        return (float) $this->movimientos()->where('tipo', MovimientoCaja::TIPO_EGRESO)->sum('monto');
    }

    /**
     * Efectivo esperado en caja: apertura + ventas en EFECTIVO del período
     * + cobros de cuenta corriente del período (ver la limitación
     * documentada en totalPagosDelPeriodo()) + ingresos manuales - egresos
     * manuales. Débito/QR/transferencia (y la venta fiada en sí, distinta
     * de su cobro posterior) no suman acá: ese dinero nunca pasa por la
     * caja física.
     */
    public function efectivoEsperadoActual(): float
    {
        $ventasPorMedio = $this->ventasPorMedioPago();

        return (float) $this->monto_apertura
            + $ventasPorMedio[Venta::MEDIO_PAGO_EFECTIVO]
            + $this->totalPagosDelPeriodo()
            + $this->totalIngresos()
            - $this->totalEgresos();
    }
}
