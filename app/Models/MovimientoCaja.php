<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ingreso o egreso de efectivo dentro de un turno de Caja (ej. un retiro
 * para cambio, un pago a un proveedor en efectivo) — ver Caja.
 */
class MovimientoCaja extends Model
{
    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_EGRESO = 'egreso';

    protected $table = 'movimientos_caja';

    protected $fillable = [
        'caja_id',
        'tipo',
        'monto',
        'concepto',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }
}
