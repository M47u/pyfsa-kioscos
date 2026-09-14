<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validación de alta pública de un Comercio nuevo (gap encontrado por el
 * usuario, no está en el documento de alcance original: hasta ahora la
 * única forma de crear un Comercio era a mano por tinker).
 *
 * A diferencia de UsuarioRequest, esta ruta vive en routes/web.php (grupo
 * `guest`, público) y corre SIN tenancy inicializada — la conexión DEFAULT
 * de Eloquent/Query Builder ya es la central, así que un `unique:users,email`
 * simple alcanza acá, sin la sintaxis `conexión.tabla`
 * (`Rule::unique(config('tenancy.database.central_connection').'.users', ...)`)
 * que necesita UsuarioRequest — ese truco es necesario ahí porque la
 * conexión default ya está cambiada al tenant cuando corre esa validación.
 */
class RegistroRequest extends FormRequest
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
            'nombre_comercio' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
