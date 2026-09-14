<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Alta de un Comercio nuevo desde /admin/comercios/create (ver
 * Admin\ComercioController) — reemplaza al registro público que existía
 * antes (ver CLAUDE.md, decisión "sin CREATE DATABASE en la app"): PyFsa
 * crea la base a mano en el panel del hosting y recién después da de alta
 * el comercio acá, indicando el nombre de esa base ya creada.
 *
 * Corre en routes/web.php, sin tenancy inicializada — la conexión DEFAULT
 * de Eloquent/Query Builder ya es la central, así que `unique:users,email`
 * simple alcanza (mismo caso que tenía RegistroRequest, "acá aplica el
 * caso simple" — el truco `Rule::unique(conexión.tabla, ...)` que sí
 * necesita UsuarioRequest es específico de rutas dentro de
 * routes/tenant.php, con la tenancy ya inicializada).
 */
class ComercioCreateRequest extends FormRequest
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
            // El nombre real de la base NO lo elige la app (los hostings
            // compartidos suelen prefijarlo con el identificador de la
            // cuenta, ej. "cpaneluser_kiosco1") — solo se valida que sea
            // un identificador de base de datos válido en MySQL (64
            // caracteres máximo, sin comillas/backticks/espacios que
            // compliquen armar la conexión más abajo).
            'nombre_base' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_base.regex' => 'El nombre de la base solo puede tener letras, números, guiones y guiones bajos.',
        ];
    }

    /**
     * Verifica que la base indicada exista y sea accesible ANTES de crear
     * nada — "me equivoqué al tipear el nombre de la base" tiene que ser
     * un error de formulario, no un comercio a medio crear. Corre DESPUÉS
     * de las reglas de arriba (withValidator se ejecuta sobre el validator
     * ya armado): si `nombre_base` ya falló el formato, ni vale la pena
     * intentar conectar.
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if ($validator->errors()->has('nombre_base')) {
                return;
            }

            if (! $this->baseEsAccesible($this->string('nombre_base')->toString())) {
                $validator->errors()->add(
                    'nombre_base',
                    'No se pudo conectar a esa base de datos. Verificá que exista (creala primero en el panel del hosting) y que el usuario tenga permisos sobre ella.'
                );
            }
        });
    }

    /**
     * Configura una conexión AL VUELO (mismo template que la conexión
     * central: mismo host/usuario/contraseña, distinta `database`) y hace
     * una consulta mínima contra ella — es la única forma real de saber si
     * la base existe y es accesible, no alcanza con mirar
     * information_schema porque el usuario de MySQL podría no tener
     * permiso ni para eso. `DB::purge()` al final descarta la conexión de
     * prueba: si la validación pasa, Admin\ComercioController arma la
     * conexión real de tenant aparte (vía Comercio::asignarBaseDeDatos() +
     * stancl/tenancy), no reusa esta.
     */
    private function baseEsAccesible(string $nombreBase): bool
    {
        $conexionTemporal = 'verificacion_alta_comercio';
        $central = config('tenancy.database.central_connection');

        config(["database.connections.{$conexionTemporal}" => array_merge(
            config("database.connections.{$central}"),
            ['database' => $nombreBase]
        )]);

        try {
            DB::connection($conexionTemporal)->select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            DB::purge($conexionTemporal);
        }
    }
}
