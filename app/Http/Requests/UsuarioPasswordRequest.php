<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validación del restablecimiento de contraseña de un empleado (ver
 * UsuarioController::updatePassword). Mismas reglas que el password del
 * alta en UsuarioRequest — el dueño la define directo, sin invitación por
 * email (no hay infraestructura de correo en este proyecto).
 */
class UsuarioPasswordRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
