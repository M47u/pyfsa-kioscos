<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * El "tenant" de stancl/tenancy — acá lo llamamos Comercio porque es lo que
 * es en el negocio: un kiosco/almacén cliente, con su propia base de datos
 * completa (ver documento de alcance, sección de arquitectura).
 */
class Comercio extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    /**
     * Estados de suscripción — administrados a mano por PyFsa desde
     * /admin/comercios (ver Admin\ComercioController), NUNCA por el
     * kiosquero. "Sin cobro automático en v1" (documento de alcance,
     * módulo 3.6): nada pasa un comercio de "prueba" a "vencida" solo,
     * es una decisión manual según el pago recibido.
     */
    public const ESTADO_PRUEBA = 'prueba';

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_VENCIDA = 'vencida';

    public const ESTADO_CANCELADA = 'cancelada';

    /**
     * GOTCHA de stancl/tenancy (Stancl\VirtualColumn\VirtualColumn, con la
     * que HasDataColumn es un alias — ver ese trait): por default, CUALQUIER
     * atributo que no esté en getCustomColumns() se saca de $attributes y
     * se empuja adentro del JSON `data` al guardar, sin importar que la
     * columna exista de verdad en la tabla `tenants`. Sin este override,
     * `estado_suscripcion`/`trial_termina_el` quedarían siempre NULL en sus
     * columnas reales (todo el valor viajaría escondido en `data`), y
     * filtrar/listar por estado en el panel admin con una query SQL normal
     * no funcionaría. `id` viene incluido en el default del trait; hay que
     * repetirlo acá porque este array reemplaza al default, no lo extiende.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'estado_suscripcion',
            'trial_termina_el',
        ];
    }

    protected function casts(): array
    {
        return [
            'trial_termina_el' => 'date',
        ];
    }

    /**
     * Prueba y activa dan acceso; vencida y cancelada no. Usado por el gate
     * del lado del comercio (ver EnsureComercioSuscripcionActiva).
     */
    public function tieneAccesoActivo(): bool
    {
        return in_array($this->estado_suscripcion, [self::ESTADO_PRUEBA, self::ESTADO_ACTIVA], true);
    }

    /**
     * Asigna la base de datos YA CREADA a mano en el panel del hosting a
     * este comercio (ver CLAUDE.md, decisión "sin CREATE DATABASE en la
     * app" — la app ya no ejecuta Jobs\CreateDatabase, ver
     * TenancyServiceProvider). Se llama ANTES de guardar un Comercio
     * nuevo, desde Admin\ComercioController::store().
     *
     * Cómo lo resuelve stancl/tenancy (verificado en
     * vendor/stancl/tenancy/src/DatabaseConfig.php::getName()): devuelve
     * `$this->tenant->getInternal('db_name')` si ya está seteado, y recién
     * si es null genera un nombre con prefijo+id (lo que pasaba antes acá,
     * vía Jobs\CreateDatabase::handle() -> database()->makeCredentials()).
     * setInternal()/getInternal() (Stancl\Tenancy\Database\Concerns\
     * HasInternalKeys) leen/escriben el atributo `tenancy_db_name` con los
     * getters/setters normales de Eloquent — es la API pública del
     * paquete para esto, no un atajo interno.
     *
     * El nombre real NO lo elige la app: los hostings compartidos suelen
     * prefijar el nombre de la base con el identificador de la cuenta
     * (ej. `cpanelUser_kiosco1`), así que viene tal cual del formulario de
     * alta — la validación de formato vive en
     * Admin\ComercioCreateRequest, no acá.
     *
     * NO hace falta agregar `tenancy_db_name` a getCustomColumns(): no es
     * una columna real de `tenants` (a diferencia de `estado_suscripcion`/
     * `trial_termina_el`), así que VirtualColumn la guarda en el JSON
     * `data` como cualquier atributo no declarado ahí — mismo caso que
     * `nombre`/`timezone` (ver esos comentarios más abajo), no el caso de
     * `estado_suscripcion`.
     */
    public function asignarBaseDeDatos(string $nombreBase): static
    {
        return $this->setInternal('db_name', $nombreBase);
    }

    /**
     * Únicas zonas horarias que un comercio puede elegir — es literalmente
     * el mercado objetivo (Formosa, Argentina + Alberdi, Paraguay; ver
     * documento de alcance, sección 04). Un <select> con los ~600 husos de
     * IANA sería complejidad de sobra para elegir entre dos países.
     *
     * `timezone` no tiene columna propia en `tenants`: stancl/tenancy
     * guarda cualquier atributo que no sea una columna real dentro del
     * JSON `data` (verificado con `$comercio->timezone = '...'; save()`),
     * así que basta con leer/escribir el atributo, sin migración.
     *
     * Se elige una sola vez, al primer uso del sistema — ver
     * ZonaHorariaController y el gate en
     * App\Http\Middleware\EnsureComercioTimezoneIsConfigured. Mientras no
     * se eligió, InitializeTenancyByAuthenticatedUser usa el default de
     * config/app.php.
     */
    public const ZONAS_HORARIAS = [
        'America/Argentina/Buenos_Aires' => 'Argentina (Formosa)',
        'America/Asuncion' => 'Paraguay (Alberdi)',
    ];
}
