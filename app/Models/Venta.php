<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    public const MEDIO_PAGO_EFECTIVO = 'efectivo';

    public const MEDIO_PAGO_TRANSFERENCIA = 'transferencia';

    public const MEDIO_PAGO_FIADO = 'fiado';

    protected $fillable = [
        'cliente_id',
        'user_id',
        'medio_pago',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    /**
     * Solo se completa cuando medio_pago = 'fiado' (ver VentaRequest).
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemVenta::class);
    }
}
