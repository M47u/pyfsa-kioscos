<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos propios (nombre + correo) desde "Mi perfil". `rol` y `comercio_id`
 * NO se validan ni se aceptan acá a propósito (PerfilController solo usa
 * validated()): nadie se promueve ni cambia de comercio por este camino.
 *
 * Cambiar el correo exige la contraseña actual (protección ante un
 * dispositivo tomado por otra persona); cambiar solo el nombre no.
 */
class PerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function cambiaEmail(): bool
    {
        $email = $this->input('email');

        // Un array (email[]=x) no es un correo: la regla 'string' de rules()
        // lo rechaza, acá solo hay que no explotar antes de validar.
        if (! is_string($email)) {
            return false;
        }

        return mb_strtolower($email) !== mb_strtolower((string) $this->user()->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuario = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            // users es CENTRAL y esta ruta corre con tenancy inicializada:
            // 'conexión.tabla' (ver UsuarioRequest). La regla incluye
            // usuarios soft-deleted (ocupan el UNIQUE de la columna).
            'email' => [
                'bail', 'required', 'string', 'email', 'max:255',
                Rule::unique(config('tenancy.database.central_connection').'.users', 'email')->ignore($usuario->id),
            ],
            'current_password' => $this->cambiaEmail()
                ? ['required', 'string', 'current_password']
                : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ese correo ya está en uso.',
            'current_password.required' => 'Para cambiar el correo tenés que ingresar tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ];
    }
}
