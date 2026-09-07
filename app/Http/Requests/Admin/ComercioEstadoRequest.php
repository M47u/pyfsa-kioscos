<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Comercio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio manual del estado de suscripción de un comercio, desde
 * /admin/comercios (ver Admin\ComercioController). Solo lo usa PyFsa —
 * el kiosquero ni sabe que este formulario existe.
 */
class ComercioEstadoRequest extends FormRequest
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
            'estado_suscripcion' => ['required', 'string', Rule::in([
                Comercio::ESTADO_PRUEBA,
                Comercio::ESTADO_ACTIVA,
                Comercio::ESTADO_VENCIDA,
                Comercio::ESTADO_CANCELADA,
            ])],
            'trial_termina_el' => ['nullable', 'date'],
        ];
    }
}
