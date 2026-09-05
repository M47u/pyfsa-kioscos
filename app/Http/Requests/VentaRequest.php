<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Venta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de una venta: un array de ítems (producto + cantidad) y un
 * medio de pago. cliente_id solo tiene sentido cuando medio_pago es
 * 'fiado' — se exige en ese caso (required_if) y se rechaza en cualquier
 * otro caso (prohibited_unless), en vez de aceptarlo y limpiarlo en
 * silencio.
 */
class VentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'medio_pago' => [
                'required',
                Rule::in([
                    Venta::MEDIO_PAGO_EFECTIVO,
                    Venta::MEDIO_PAGO_TRANSFERENCIA,
                    Venta::MEDIO_PAGO_FIADO,
                ]),
            ],
            'cliente_id' => [
                'nullable',
                'integer',
                'required_if:medio_pago,'.Venta::MEDIO_PAGO_FIADO,
                'prohibited_unless:medio_pago,'.Venta::MEDIO_PAGO_FIADO,
                Rule::exists('clientes', 'id'),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ];
    }
}
