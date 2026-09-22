<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la apertura de un turno de caja (ver CajaController::abrir()).
 * Que no haya ya una caja abierta se valida en el controller, no acá: es
 * una regla de estado del comercio, no del dato en sí (mismo criterio que
 * el chequeo de stock en VentaController::store, que vive en el controller
 * por depender de un lock/estado que el FormRequest no tiene motivo de
 * conocer).
 */
class AbrirCajaRequest extends FormRequest
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
            'monto_apertura' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
