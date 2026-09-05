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

    /**
     * El saldo de fiado NO se guarda en columna propia: se calcula, igual
     * que Producto::stockActual(). Hoy se calcula solo como la suma de las
     * ventas fiadas del cliente, porque todavía no existe el módulo de
     * Pagos. Cuando exista, los pagos van a tener que RESTARSE acá
     * (saldo = ventas fiadas - pagos) — ver documento de alcance.
     */
    public function saldo(): float
    {
        return (float) $this->ventas()
            ->where('medio_pago', Venta::MEDIO_PAGO_FIADO)
            ->sum('total');
    }
}
