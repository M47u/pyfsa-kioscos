<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de alta de Cliente. Nunca valida saldo: eso se calcula
 * (ver Cliente::saldo()), no se carga a mano.
 */
class ClienteRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'limite_credito' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
