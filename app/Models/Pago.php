<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un pago que un cliente hace contra su cuenta corriente (fiado). Resta del
 * saldo calculado por Cliente::saldo() — ver ese método.
 */
class Pago extends Model
{
    protected $fillable = [
        'cliente_id',
        'monto',
        'user_id',
        // Offline (ver ClienteController::registrarPago y CLAUDE.md,
        // arquitectura offline): viene del frontend (crypto.randomUUID())
        // en todo pago, online u offline — mismo criterio que
        // Venta::uuid_dispositivo.
        'uuid_dispositivo',
        // Los tres de abajo nunca vienen de un form del usuario: los
        // setea ClienteController::anularPago() a mano (ver Venta::$fillable
        // por el mismo motivo).
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Nunca se borra ni se edita un pago (corrección de error humano — ver
     * documento de alcance): anular deja anulado_en seteado y Cliente::saldo()
     * deja de restarlo. Más simple que anular una Venta porque un pago no
     * toca stock, no hay nada que revertir aparte de sus propias columnas.
     */
    public function estaAnulado(): bool
    {
        return $this->anulado_en !== null;
    }
}
