<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    /**
     * Default de `controla_stock` en el objeto EN MEMORIA, no solo en la
     * columna de la base. Bug real encontrado por el test de regresión
     * `bajo_minimo_segun_stock_actual`: `Producto::create([...])` sin pasar
     * `controla_stock` explícito deja la columna en `true` por el default
     * de la migración, pero Eloquent NO relee la fila después del INSERT
     * (mismo gotcha ya documentado en otros lados de este proyecto, ver
     * `TenantTestCase::setUp()`) — sin este default acá, el objeto recién
     * creado quedaba con `controla_stock` NULL/falsy en memoria, y
     * `bajoMinimo()` devolvía `false` siempre para un producto recién
     * creado en el mismo request (ej. el alta manual, la importación CSV).
     */
    protected $attributes = [
        'controla_stock' => true,
    ];

    protected $fillable = [
        'nombre',
        'codigo_barras',
        'precio_costo',
        'precio_venta',
        'stock_minimo',
        // Control de stock opcional (gap encontrado por el usuario): un
        // producto con controla_stock=false nunca bloquea una venta por
        // falta de stock ni se marca sincronizada_con_stock_insuficiente
        // (ver VentaRequest::withValidator y VentaController::crearVenta) —
        // pensado para artículos que el kiosquero no quiere/puede llevar
        // en inventario (bolsas sueltas, reventa variable).
        'controla_stock',
    ];

    protected function casts(): array
    {
        return [
            'precio_costo' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'stock_minimo' => 'integer',
            'controla_stock' => 'boolean',
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

    /**
     * Un producto con controla_stock=false nunca está "bajo mínimo": no le
     * llevamos la cuenta, no tiene sentido alertar sobre algo que el
     * kiosquero decidió explícitamente no medir.
     */
    public function bajoMinimo(): bool
    {
        return $this->controla_stock && $this->stockActual() < $this->stock_minimo;
    }

    /**
     * Búsqueda tolerante a texto parcial y a orden de palabras: "coc cola"
     * debe encontrar "Coca Cola", "alfajor milka" debe encontrar "Alfajor
     * Milka Chocolate 55g" (documento de alcance, módulo de Ventas). Se
     * parte el término en palabras y se exige que TODAS aparezcan como
     * substring de `nombre` (AND, no OR) — un simple LIKE %termino completo%
     * solo encontraría coincidencias exactas de la frase entera, no de sus
     * partes en cualquier orden. El código de barras se sigue comparando
     * como frase completa (un lector nunca lo manda partido en palabras).
     *
     * Se ignoran palabras sueltas de un solo caracter (ver array_filter):
     * en un LIKE %x% una palabra de 1 letra matchea casi cualquier nombre y
     * no aporta nada a filtrar, además de ser el caso típico de un espacio
     * de más tipeado por accidente.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        // Escapar metacaracteres de LIKE (% y _) y la barra invertida que
        // los escapa a ellos, para que un término de búsqueda que los
        // contenga literalmente (código de barras, nombre de producto) no
        // sea interpretado como wildcard y matchee de más.
        $escapar = fn (string $valor) => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);

        $terminoCompleto = $escapar($term);
        $palabras = array_filter(
            preg_split('/\s+/', trim($term)) ?: [],
            fn (string $palabra) => mb_strlen($palabra) > 1
        );

        return $query->where(function (Builder $query) use ($terminoCompleto, $palabras, $escapar) {
            $query->where('codigo_barras', 'like', "%{$terminoCompleto}%");

            if ($palabras !== []) {
                $query->orWhere(function (Builder $query) use ($palabras, $escapar) {
                    foreach ($palabras as $palabra) {
                        $query->where('nombre', 'like', '%'.$escapar($palabra).'%');
                    }
                });
            } else {
                // Término de una sola letra (o vacío tras recortar
                // espacios): no hay palabras "útiles" para el AND de
                // arriba — cae al comportamiento simple, comparar el
                // término completo tal cual contra nombre.
                $query->orWhere('nombre', 'like', "%{$terminoCompleto}%");
            }
        });
    }
}
