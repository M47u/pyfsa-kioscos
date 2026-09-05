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
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
