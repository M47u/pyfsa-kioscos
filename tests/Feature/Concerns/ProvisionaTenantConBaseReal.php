<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Models\Comercio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * La app ya no ejecuta CREATE/DROP DATABASE (ver CLAUDE.md, decisión "sin
 * CREATE DATABASE en la app" y TenancyServiceProvider — se sacó
 * Jobs\CreateDatabase/Jobs\DeleteDatabase del pipeline): en producción,
 * PyFsa crea la base a mano en el panel del hosting antes de dar de alta
 * el comercio. Cualquier test que necesite un Comercio real — aunque sea
 * uno "de relleno", solo para probar aislamiento entre comercios — tiene
 * que autoabastecerse la base igual que va a hacerlo PyFsa a mano:
 * `Comercio::create()` sin una base real ahora TIRA
 * TenantDatabaseDoesNotExistException, porque Jobs\MigrateDatabase sigue
 * en el pipeline e intenta migrar contra el nombre generado (que nunca
 * existió) — verificado, no es una suposición.
 */
trait ProvisionaTenantConBaseReal
{
    /**
     * Nombres de base creados con crearComercioConBaseReal() en este test,
     * para poder borrarlos todos de una en borrarComerciosConBaseReal()
     * sin que cada test tenga que llevar la cuenta a mano.
     *
     * @var list<string>
     */
    private array $basesDeTenantCreadasEnEsteTest = [];

    /**
     * Crea una base con nombre único (sentencia directa contra la conexión
     * central — en local el usuario `root` tiene permiso, a diferencia del
     * usuario del hosting real) y un Comercio apuntado a ella. Si algo
     * falla DESPUÉS de crear la base (el save() del Comercio, por ejemplo),
     * la borra antes de relanzar la excepción — no hay que dejar una base
     * huérfana solo porque el test iba a fallar de todos modos.
     */
    protected function crearComercioConBaseReal(array $atributos = []): Comercio
    {
        $nombreBase = 'test_tenant_'.Str::random(12);

        DB::connection(config('tenancy.database.central_connection'))
            ->statement("CREATE DATABASE `{$nombreBase}`");

        $this->basesDeTenantCreadasEnEsteTest[] = $nombreBase;

        try {
            $comercio = new Comercio($atributos);
            $comercio->asignarBaseDeDatos($nombreBase);
            $comercio->save();

            return $comercio;
        } catch (Throwable $e) {
            $this->borrarComerciosConBaseReal();

            throw $e;
        }
    }

    /**
     * Crea una base con nombre único SIN comercio asociado — para simular
     * el paso previo real (PyFsa la crea a mano en el panel del hosting)
     * en tests que necesitan probar el formulario de alta
     * (Admin\ComercioCreateRequest) contra una base que YA existe, antes
     * de que exista ningún Comercio apuntando a ella. Se trackea igual que
     * crearComercioConBaseReal() para que borrarComerciosConBaseReal() la
     * limpie sin que el test tenga que acordarse a mano.
     */
    protected function crearBaseDeDatosSuelta(): string
    {
        $nombreBase = 'test_tenant_'.Str::random(12);

        DB::connection(config('tenancy.database.central_connection'))
            ->statement("CREATE DATABASE `{$nombreBase}`");

        $this->basesDeTenantCreadasEnEsteTest[] = $nombreBase;

        return $nombreBase;
    }

    /**
     * Borra TODAS las bases creadas con crearComercioConBaseReal() en este
     * test — llamar desde tearDown(). No hace falta pasarle el Comercio:
     * Comercio::delete() ya no toca ninguna base (ver TenancyServiceProvider),
     * así que borrar la fila y borrar la base son dos pasos independientes
     * de acá en adelante.
     */
    protected function borrarComerciosConBaseReal(): void
    {
        $conexionCentral = DB::connection(config('tenancy.database.central_connection'));

        foreach ($this->basesDeTenantCreadasEnEsteTest as $nombreBase) {
            $conexionCentral->statement("DROP DATABASE IF EXISTS `{$nombreBase}`");
        }

        $this->basesDeTenantCreadasEnEsteTest = [];
    }
}
