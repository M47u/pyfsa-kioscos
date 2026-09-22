<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de alta y edición de Producto. Nunca valida cantidad/stock:
 * eso se maneja en ReponerStockRequest, porque el stock no se edita
 * directamente (se calcula desde movimientos_stock).
 */
class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La columna `stock_minimo` es NOT NULL con default 0 (ver migración
     * de productos). Si el form llega con el campo vacío,
     * ConvertEmptyStringsToNull lo vuelve null, que la regla 'nullable'
     * deja pasar, y el insert/update explota contra la DB en vez de dar
     * un error de validación prolijo. Normalizamos acá para que el
     * default de negocio quede explícito antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('stock_minimo') === null) {
            $this->merge(['stock_minimo' => 0]);
        }

        if ($this->input('stock_inicial') === null) {
            $this->merge(['stock_inicial' => 0]);
        }

        // Checkbox HTML: si no viene tildado, el navegador no manda el
        // campo — sin esto, 'controla_stock' quedaría ausente del array
        // validado en vez de explícitamente false. Bug real encontrado por
        // test: el default acá tiene que ser `false` (checkbox ausente =
        // desmarcado), no `true` — con `true` como default, desmarcar el
        // checkbox nunca se distinguía de dejarlo tildado.
        $this->merge(['controla_stock' => $this->boolean('controla_stock')]);
    }

    public function rules(): array
    {
        return self::reglas($this->route('producto'));
    }

    /**
     * Reglas de validación de un Producto, factorizadas como método estático
     * para que ProductoImportController (alta masiva por CSV) las reuse
     * fila por fila en vez de duplicarlas — ahí no hay un {producto} de
     * ruta para ignorar en la unicidad de codigo_barras (siempre es alta
     * nueva), de ahí el parámetro opcional en vez de leerlo de la request.
     *
     * @return array<string, mixed>
     */
    public static function reglas(mixed $productoAIgnorar = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'codigo_barras' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('productos', 'codigo_barras')->ignore($productoAIgnorar),
            ],
            'precio_costo' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'integer', 'min:0'],
            // Control de stock opcional (ver Producto::bajoMinimo() y
            // VentaController::crearVenta): default true vía
            // prepareForValidation(), preserva el comportamiento actual
            // para todo producto ya cargado o dado de alta sin tocar el
            // checkbox.
            'controla_stock' => ['boolean'],
            // Solo se usa en el alta (ver ProductoController::store). No es
            // columna de `productos` — genera un MovimientoStock de
            // reposición, porque el stock nunca se guarda directo.
            'stock_inicial' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
