<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Rastro de las acciones que PyFsa ejecuta sobre los comercios desde el
 * panel admin (ver Admin\ComercioController y Admin\AuditoriaController).
 * Tabla CENTRAL — mismo criterio que `users`.
 *
 * Nunca se edita ni se borra desde la app: es solo-append (misma filosofía
 * que MovimientoStock o que la anulación de ventas — ver CLAUDE.md).
 */
class RegistroAuditoria extends Model
{
    /**
     * Mismo motivo que en User: hoy todas las rutas que escriben acá son
     * centrales (/admin, sin tenancy inicializada), pero si mañana se
     * auditara algo desde una ruta de routes/tenant.php, la conexión
     * DEFAULT de Eloquent ya sería la del tenant — que no tiene esta tabla.
     * Forzar la central de entrada evita ese bug antes de que exista.
     */
    use CentralConnection;

    /**
     * Eloquent pluralizaría RegistroAuditoria como `registro_auditorias`
     * (pluraliza solo la última palabra). El nombre real de la tabla lleva
     * el plural en la primera, como en español.
     */
    protected $table = 'registros_auditoria';

    /**
     * Un registro de auditoría se crea y no se toca nunca más: no tiene
     * sentido una columna updated_at, y Eloquent respeta este null sin
     * nada más que hacer (ver Model::updateTimestamps()).
     */
    public const UPDATED_AT = null;

    /**
     * Acciones auditadas. El formato `recurso.evento` deja lugar a que
     * crezcan sin colisionar (ej. un futuro `usuario.password_restablecida`).
     */
    public const ACCION_COMERCIO_CREADO = 'comercio.creado';

    public const ACCION_COMERCIO_ESTADO_ACTUALIZADO = 'comercio.estado_actualizado';

    /**
     * Provisionar un admin de plataforma es la acción MÁS privilegiada del
     * sistema — una cuenta con `is_admin` ve y toca TODOS los comercios —,
     * así que es la que menos puede quedar sin rastro. Se registra desde
     * CrearAdminCommand, o sea desde consola: `user_id` va a quedar null
     * (no hay sesión), igual que `ip`/`user_agent`. Es esperado, no un
     * bug: el "quién" de una acción de consola es el acceso al servidor,
     * que se audita afuera de esta app.
     */
    public const ACCION_ADMIN_CREADO = 'admin.creado';

    public const ACCION_ADMIN_PROMOVIDO = 'admin.promovido';

    /**
     * Etiquetas legibles para la vista — separadas del valor guardado a
     * propósito: cambiar el texto de la pantalla no debe reescribir el
     * historial ya persistido.
     */
    public const ETIQUETAS = [
        self::ACCION_COMERCIO_CREADO => 'Comercio creado',
        self::ACCION_COMERCIO_ESTADO_ACTUALIZADO => 'Estado de suscripción actualizado',
        self::ACCION_ADMIN_CREADO => 'Administrador de plataforma creado',
        self::ACCION_ADMIN_PROMOVIDO => 'Usuario promovido a administrador',
    ];

    protected $fillable = [
        'user_id',
        'accion',
        'comercio_id',
        'detalles',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'detalles' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Punto de entrada único desde los controllers y comandos — resuelve
     * solo el "quién" (el admin autenticado) y el "de dónde" (IP +
     * user-agent) para que ningún call site se olvide de pasarlos.
     *
     * Los tres quedan nullable y en NULL cuando esto corre desde consola
     * (CrearAdminCommand): no hay sesión de la cual sacar el usuario ni
     * request HTTP de la cual sacar la IP. Es esperado, no un bug — el
     * quién/de dónde de una acción de consola es el acceso al servidor,
     * que se audita fuera de esta app.
     *
     * DOS FORMAS DE DETECTAR "esto no es una request HTTP" que NO sirven,
     * las dos verificadas contra el framework, no supuestas:
     *
     * 1. Confiar en que en consola `request()` viene vacía. No viene: el
     *    kernel de consola bootea SetRequestForConsole (ver
     *    vendor/laravel/framework/.../Bootstrap/SetRequestForConsole.php),
     *    que arma una Request FALSA con `Request::create(config('app.url'))`
     *    — y Symfony rellena ahí REMOTE_ADDR = 127.0.0.1 y
     *    HTTP_USER_AGENT = 'Symfony' por default. Sin este guard, cada
     *    `php artisan admin:crear` en producción dejaría un registro que
     *    dice que la acción vino de 127.0.0.1 con un navegador llamado
     *    "Symfony": un log que miente, peor que un log vacío.
     * 2. app()->runningInConsole(). Mira \PHP_SAPI, así que bajo PHPUnit
     *    devuelve true SIEMPRE — también en los tests que sí pegan por
     *    HTTP. Gatear con eso apagaría la captura justo donde hay que
     *    probarla, y el bug volvería sin que ningún test se entere.
     *
     * Lo que sí distingue los dos casos es si hay una RUTA siendo
     * atendida: la request falsa de consola nunca pasa por el router, así
     * que `route()` es null; cualquier acción admin auditada llega desde
     * un controller, o sea con ruta resuelta, en producción y en tests por
     * igual.
     *
     * @param  array<string, mixed>  $detalles
     */
    public static function registrar(string $accion, ?string $comercioId = null, array $detalles = []): self
    {
        $request = request();
        $esHttpReal = $request->route() !== null;

        return self::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'comercio_id' => $comercioId,
            'detalles' => $detalles === [] ? null : $detalles,
            'ip' => $esHttpReal ? $request->ip() : null,
            // Un user-agent falsificado puede venir de cualquier largo: se
            // recorta al de la columna para que un header gigante no tire
            // abajo la escritura del registro (perder el rastro entero por
            // no poder guardar un dato accesorio sería el peor final).
            'user_agent' => $esHttpReal
                ? (Str::limit((string) $request->userAgent(), 511, '') ?: null)
                : null,
        ]);
    }

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->accion] ?? $this->accion;
    }
}
