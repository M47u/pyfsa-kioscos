<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = [
        'nombre',
        'telefono',
        'limite_credito',
    ];

    protected function casts(): array
    {
        return [
            'limite_credito' => 'decimal:2',
        ];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * El saldo de fiado NO se guarda en columna propia: se calcula, igual
     * que Producto::stockActual(). Es la suma de las ventas fiadas del
     * cliente MENOS la suma de sus pagos. Un resultado negativo (pagó más
     * de lo que debía) es información válida y no se clampea a 0: no hay
     * ninguna regla de negocio que lo impida.
     */
    public function saldo(): float
    {
        $totalFiado = (float) $this->ventas()
            ->where('medio_pago', Venta::MEDIO_PAGO_FIADO)
            ->sum('total');

        $totalPagado = (float) $this->pagos()->sum('monto');

        return $totalFiado - $totalPagado;
    }

    /**
     * Mismo patrón que Producto::bajoMinimo(): solo alerta, no bloquea (ver
     * VentaController::store y la decisión confirmada en CLAUDE.md/documento
     * de alcance — a diferencia del stock, el límite de crédito es una
     * cuestión de confianza que el kiosquero puede decidir pasar por alto).
     *
     * Acepta un $saldo ya calculado opcionalmente para que un call site que
     * ya lo necesitó (por ejemplo para mostrarlo) se lo pase acá en vez de
     * hacer que este método lo recalcule de cero — mismo patrón que el bug
     * de doble stockActual() ya arreglado en productos/index. Si no se
     * pasa nada, se comporta igual que antes.
     */
    public function superaLimite(?float $saldo = null): bool
    {
        return ($saldo ?? $this->saldo()) > (float) $this->limite_credito;
    }
}
