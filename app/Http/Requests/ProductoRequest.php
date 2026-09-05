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
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $producto = $this->route('producto');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'codigo_barras' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('productos', 'codigo_barras')->ignore($producto),
            ],
            'precio_costo' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
