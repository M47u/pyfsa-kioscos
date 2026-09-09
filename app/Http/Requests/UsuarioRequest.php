<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validación de alta de empleado (documento de alcance, módulo 3.5
 * "Usuarios"). Solo alta: no hay edición ni baja de usuarios en esta
 * primera versión. Nunca valida `rol` ni `comercio_id` — eso lo fuerza el
 * controller (siempre ROL_EMPLEADO, siempre el comercio actual), nunca el
 * form, para que no se pueda dar de alta otro dueño desde acá.
 */
class UsuarioRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // unique global (users es tabla central, compartida por todos
            // los comercios): un mismo email no puede loguear en dos
            // comercios distintos.
            //
            // Conexión explícita (mismo gotcha que CentralConnection en
            // User): este request se valida DENTRO de routes/tenant.php,
            // con la tenancy ya inicializada, así que la conexión DEFAULT
            // de Eloquent/Query Builder ya es la del tenant. La regla
            // `unique:tabla,columna` sin conexión explícita consultaría
            // `users` contra esa base del tenant (que no tiene esa tabla)
            // en vez de la central.
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // NO excluye soft-deleted a propósito (a diferencia de la
                // validación): la columna `email` tiene un UNIQUE real a
                // nivel de base (users_email_unique) que no sabe nada de
                // `deleted_at` — un WHERE en la regla de Laravel solo haría
                // que la VALIDACIÓN deje pasar el alta, para que después
                // explote con un 500 real por violar el índice único al
                // insertar. Limitación conocida: el email de un empleado
                // eliminado queda reservado (no se puede reusar) hasta que
                // se resuelva con una migración aparte (ej. índice único
                // parcial vía columna generada) — no es parte de este
                // cambio.
                Rule::unique(config('tenancy.database.central_connection').'.users', 'email'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
