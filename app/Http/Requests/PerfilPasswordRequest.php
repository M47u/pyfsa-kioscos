<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Cambio de contraseña propio desde "Mi perfil" (ver PerfilController::
 * updatePassword). Pide la actual y exige que la nueva sea distinta —
 * importante tras un reset de PyFsa, que conoce la contraseña generada.
 */
class PerfilPasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Ingresá tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La contraseña nueva tiene que ser distinta de la actual.',
        ];
    }
}
