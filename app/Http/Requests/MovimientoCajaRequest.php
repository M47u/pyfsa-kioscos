<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MovimientoCaja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de un ingreso/egreso manual dentro de un turno de caja (ver
 * CajaController::registrarMovimiento()) — ej. un retiro para cambio, un
 * pago a un proveedor en efectivo. `concepto` es obligatorio a propósito
 * (a diferencia del motivo de una anulación): un movimiento de caja sin
 * ninguna descripción no sirve para nada al revisar la diferencia después.
 */
class MovimientoCajaRequest extends FormRequest
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
            'tipo' => ['required', Rule::in([MovimientoCaja::TIPO_INGRESO, MovimientoCaja::TIPO_EGRESO])],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'concepto' => ['required', 'string', 'max:255'],
        ];
    }
}
