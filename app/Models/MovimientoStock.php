<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada fila es un movimiento de stock (reposición o venta) de un producto.
 * El stock actual de un producto NUNCA se guarda ni se edita directamente:
 * se calcula sumando la columna `cantidad` de todos sus movimientos
 * (ver Producto::stockActual()).
 */
class MovimientoStock extends Model
{
    public const TIPO_REPOSICION = 'reposicion';

    // Usado por VentaController::store con cantidad negativa.
    public const TIPO_VENTA = 'venta';

    // Usado por VentaController::anular con cantidad POSITIVA (reversa
    // exacta de TIPO_VENTA): repone el stock de una venta anulada sin
    // borrar ni editar el movimiento original, mismo principio que el
    // resto del sistema.
    public const TIPO_ANULACION_VENTA = 'anulacion_venta';

    protected $table = 'movimientos_stock';

    protected $fillable = [
        'producto_id',
        'tipo',
        'cantidad',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
