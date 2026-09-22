<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del cierre de un turno de caja (ver CajaController::cerrar()).
 * `efectivo_contado` es lo único que carga el cajero a mano — el efectivo
 * ESPERADO y la diferencia los calcula el servidor (ver
 * Caja::efectivoEsperadoActual()), nunca se confían al cliente.
 */
class CerrarCajaRequest extends FormRequest
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
            'efectivo_contado' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ];
    }
}
