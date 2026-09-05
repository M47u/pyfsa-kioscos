<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada fila es un producto vendido dentro de una Venta. El nombre de tabla
 * (items_venta) sigue el mismo patrón sustantivo+calificador que
 * movimientos_stock.
 */
class ItemVenta extends Model
{
    protected $table = 'items_venta';

    protected $fillable = [
        'venta_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            // Snapshot del precio de venta al momento de vender — ver
            // migración de items_venta y VentaController::store. Nunca se
            // recalcula a partir del precio actual del producto.
            'precio_unitario' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
