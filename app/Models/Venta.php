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
        // Offline (ver VentaController::store y CLAUDE.md, arquitectura
        // offline): uuid_dispositivo viene del frontend (crypto.randomUUID())
        // en toda venta, online u offline. sincronizada_con_stock_insuficiente
        // la setea el controller cuando una venta encolada offline se
        // sincroniza dejando stock negativo.
        'uuid_dispositivo',
        'sincronizada_con_stock_insuficiente',
        // Los tres de abajo nunca vienen de un form del usuario: los
        // setea VentaController::anular() a mano, con datos ya validados
        // (auth()->id(), now()). Están en $fillable para poder usar
        // update([...]) ahí en vez de forceFill().
        'anulada_en',
        'anulada_por',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'sincronizada_con_stock_insuficiente' => 'boolean',
            'anulada_en' => 'datetime',
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

    /**
     * Nunca se borra ni se edita una venta (corrección de error humano —
     * ver documento de alcance): anular deja anulada_en seteado y revierte
     * el stock con un MovimientoStock nuevo (ver VentaController::anular).
     * Esta venta y sus items/movimientos originales quedan intactos, es
     * historia.
     */
    public function estaAnulada(): bool
    {
        return $this->anulada_en !== null;
    }
}
