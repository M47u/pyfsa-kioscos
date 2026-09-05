<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $fillable = [
        'nombre',
        'codigo_barras',
        'precio_costo',
        'precio_venta',
        'stock_minimo',
    ];

    protected function casts(): array
    {
        return [
            'precio_costo' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'stock_minimo' => 'integer',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class);
    }

    /**
     * El stock actual NO se guarda en columna propia: se calcula sumando
     * los movimientos (reposiciones suman, ventas restan).
     */
    public function stockActual(): int
    {
        return (int) $this->movimientos()->sum('cantidad');
    }

    public function bajoMinimo(): bool
    {
        return $this->stockActual() < $this->stock_minimo;
    }

    /**
     * Búsqueda por nombre (like) o código de barras (like) — compatible con
     * tipear a mano o escanear con lector USB/Bluetooth, que solo "tipea"
     * el código en el mismo input.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('nombre', 'like', "%{$term}%")
                ->orWhere('codigo_barras', 'like', "%{$term}%");
        });
    }
}
