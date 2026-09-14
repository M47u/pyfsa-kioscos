<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Comercio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\ProvisionaTenantConBaseReal;
use Tests\TestCase;

/**
 * `php artisan admin:crear` (ver App\Console\Commands\CrearAdminCommand):
 * la única forma soportada de marcar a alguien como admin de plataforma.
 * No hereda de TenantTestCase — `users` es CENTRAL y el comando no toca
 * ninguna base de tenant.
 *
 * La regresión que justifica el test más importante de todos: el snippet
 * de tinker que estaba documentado antes (`User::where(...)->update(
 * ['is_admin' => true])`) era un no-op silencioso porque `is_admin` no
 * está en User::$fillable. Acá se verifica que el comando SÍ persiste el
 * flag, sin haber tocado ese guard de mass-assignment.
 */
class CrearAdminCommandTest extends TestCase
{
    use ProvisionaTenantConBaseReal;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Comercio::all()->each->delete();
        $this->borrarComerciosConBaseReal();

        parent::tearDown();
    }

    public function test_promueve_a_un_usuario_ya_existente(): void
    {
        $usuario = User::factory()->create([
            'email' => 'ya-existe@pyfsa.test',
            'is_admin' => false,
        ]);

        $this->artisan('admin:crear', ['email' => 'ya-existe@pyfsa.test'])
            ->assertSuccessful();

        $this->assertTrue($usuario->fresh()->is_admin);
    }

    public function test_crea_un_usuario_nuevo_con_la_password_hasheada(): void
    {
        $this->artisan('admin:crear', [
            'email' => 'nuevo@pyfsa.test',
            '--nombre' => 'Admin Nuevo',
            '--password' => 'secreto-largo-123',
        ])->assertSuccessful();

        $usuario = User::where('email', 'nuevo@pyfsa.test')->first();

        $this->assertNotNull($usuario);
        $this->assertSame('Admin Nuevo', $usuario->name);
        $this->assertTrue($usuario->is_admin);
        $this->assertNull($usuario->comercio_id);
        $this->assertNotSame('secreto-largo-123', $usuario->password);
        $this->assertTrue(Hash::check('secreto-largo-123', $usuario->password));
    }

    /**
     * El email se normaliza a minúsculas antes de buscar/crear: sin esto,
     * "Admin@..." y "admin@..." serían dos filas distintas para Eloquent
     * (aunque MySQL las trate igual por collation) y el comando terminaría
     * chocando contra el UNIQUE de la columna en vez de promover.
     */
    public function test_el_email_se_normaliza_a_minusculas(): void
    {
        $usuario = User::factory()->create(['email' => 'mayusculas@pyfsa.test']);

        $this->artisan('admin:crear', ['email' => 'MAYUSCULAS@PyFsa.TEST'])
            ->assertSuccessful();

        $this->assertTrue($usuario->fresh()->is_admin);
        $this->assertSame(1, User::where('email', 'like', '%mayusculas@pyfsa.test')->count());
    }

    /**
     * Decisión explícita: un admin de PyFsa PUEDE además ser dueño de su
     * propio comercio de prueba. Se avisa (esa cuenta pasa a ver su
     * comercio Y el panel de todos), pero no se bloquea.
     */
    public function test_advierte_pero_promueve_igual_a_un_usuario_que_pertenece_a_un_comercio(): void
    {
        $comercio = $this->crearComercioConBaseReal();
        $usuario = User::factory()->create([
            'email' => 'dueno-y-admin@pyfsa.test',
            'comercio_id' => $comercio->id,
        ]);

        $this->artisan('admin:crear', ['email' => 'dueno-y-admin@pyfsa.test'])
            ->expectsOutputToContain('pertenece al comercio')
            ->assertSuccessful();

        $this->assertTrue($usuario->fresh()->is_admin);
    }

    /**
     * `users` usa SoftDeletes (ver el modelo): la fila dada de baja sigue
     * ocupando el email en el UNIQUE de la columna, así que buscarla con
     * withTrashed() y restaurarla es la única salida que no termina en un
     * SQLSTATE[23000] ilegible.
     */
    public function test_restaura_y_promueve_a_un_usuario_dado_de_baja(): void
    {
        $usuario = User::factory()->create(['email' => 'de-baja@pyfsa.test']);
        $usuario->delete();

        $this->artisan('admin:crear', ['email' => 'de-baja@pyfsa.test'])
            ->expectsOutputToContain('eliminación lógica')
            ->assertSuccessful();

        $restaurado = User::where('email', 'de-baja@pyfsa.test')->first();

        $this->assertNotNull($restaurado);
        $this->assertTrue($restaurado->is_admin);
    }

    public function test_es_idempotente_si_el_usuario_ya_era_admin(): void
    {
        $usuario = User::factory()->create([
            'email' => 'ya-admin@pyfsa.test',
            'is_admin' => true,
        ]);

        $this->artisan('admin:crear', ['email' => 'ya-admin@pyfsa.test'])
            ->expectsOutputToContain('ya era administrador')
            ->assertSuccessful();

        $this->assertTrue($usuario->fresh()->is_admin);
    }

    /**
     * Sin terminal interactiva no hay a quién preguntarle el nombre, y
     * crear un admin sin nombre sería peor que fallar.
     */
    public function test_falla_sin_nombre_cuando_hay_que_crear_el_usuario(): void
    {
        $this->artisan('admin:crear', [
            'email' => 'sin-nombre@pyfsa.test',
            '--password' => 'secreto-largo-123',
            '--no-interaction' => true,
        ])->assertFailed();

        $this->assertNull(User::where('email', 'sin-nombre@pyfsa.test')->first());
    }

    /**
     * Camino interactivo (el default cuando no se pasa --password): la
     * contraseña se pide dos veces y no se escribe en ningún lado.
     */
    public function test_pide_nombre_y_password_de_forma_interactiva(): void
    {
        $this->artisan('admin:crear', ['email' => 'interactivo@pyfsa.test'])
            ->expectsQuestion('Nombre del administrador', 'Admin Interactivo')
            ->expectsQuestion('Contraseña del administrador', 'otro-secreto-456')
            ->expectsQuestion('Repetí la contraseña', 'otro-secreto-456')
            ->assertSuccessful();

        $usuario = User::where('email', 'interactivo@pyfsa.test')->first();

        $this->assertNotNull($usuario);
        $this->assertTrue($usuario->is_admin);
        $this->assertTrue(Hash::check('otro-secreto-456', $usuario->password));
    }

    /**
     * Crear un admin de plataforma es la acción más privilegiada del
     * sistema (esa cuenta ve y toca TODOS los comercios) y hasta acá era la
     * única que no dejaba ningún rastro. `user_id` queda null porque un
     * comando de consola no tiene sesión — está previsto en el docblock de
     * RegistroAuditoria::registrar().
     */
    public function test_crear_un_admin_deja_un_registro_de_auditoria(): void
    {
        $this->artisan('admin:crear', [
            'email' => 'auditado@pyfsa.test',
            '--nombre' => 'Admin Auditado',
            '--password' => 'secreto-largo-123',
        ])->assertSuccessful();

        $registro = RegistroAuditoria::sole();
        $usuario = User::where('email', 'auditado@pyfsa.test')->sole();

        $this->assertSame(RegistroAuditoria::ACCION_ADMIN_CREADO, $registro->accion);
        $this->assertNull($registro->user_id);
        $this->assertNull($registro->comercio_id);
        $this->assertSame('auditado@pyfsa.test', $registro->detalles['email']);
        $this->assertSame('Admin Auditado', $registro->detalles['nombre']);
        $this->assertSame($usuario->id, $registro->detalles['usuario_id']);
    }

    public function test_promover_a_un_usuario_deja_un_registro_de_auditoria(): void
    {
        $usuario = User::factory()->create(['email' => 'promovido@pyfsa.test']);

        $this->artisan('admin:crear', ['email' => 'promovido@pyfsa.test'])
            ->assertSuccessful();

        $registro = RegistroAuditoria::sole();

        $this->assertSame(RegistroAuditoria::ACCION_ADMIN_PROMOVIDO, $registro->accion);
        $this->assertSame('promovido@pyfsa.test', $registro->detalles['email']);
        $this->assertSame($usuario->id, $registro->detalles['usuario_id']);
        $this->assertFalse($registro->detalles['restaurado_de_baja']);
        $this->assertFalse($registro->detalles['ya_era_admin']);
    }

    /**
     * Devolverle el acceso a todos los comercios a una cuenta que estaba
     * dada de baja es justo lo que un log de seguridad tiene que mostrar,
     * así que el detalle lleva esa bandera aparte.
     */
    public function test_el_registro_marca_si_el_usuario_venia_dado_de_baja(): void
    {
        $usuario = User::factory()->create(['email' => 'resucitado@pyfsa.test']);
        $usuario->delete();

        $this->artisan('admin:crear', ['email' => 'resucitado@pyfsa.test'])
            ->assertSuccessful();

        $registro = RegistroAuditoria::sole();

        $this->assertSame(RegistroAuditoria::ACCION_ADMIN_PROMOVIDO, $registro->accion);
        $this->assertTrue($registro->detalles['restaurado_de_baja']);
    }

    /**
     * El caso idempotente es un no-op de verdad: no se toca nada, así que
     * tampoco se ensucia el log con una fila que no representa un cambio.
     */
    public function test_promover_a_alguien_que_ya_era_admin_no_deja_registro(): void
    {
        User::factory()->create([
            'email' => 'ya-admin-auditado@pyfsa.test',
            'is_admin' => true,
        ]);

        $this->artisan('admin:crear', ['email' => 'ya-admin-auditado@pyfsa.test'])
            ->assertSuccessful();

        $this->assertSame(0, RegistroAuditoria::count());
    }

    public function test_falla_si_las_passwords_interactivas_no_coinciden(): void
    {
        $this->artisan('admin:crear', ['email' => 'distraido@pyfsa.test'])
            ->expectsQuestion('Nombre del administrador', 'Admin Distraído')
            ->expectsQuestion('Contraseña del administrador', 'una-cosa-123')
            ->expectsQuestion('Repetí la contraseña', 'otra-cosa-456')
            ->assertFailed();

        $this->assertNull(User::where('email', 'distraido@pyfsa.test')->first());
    }
}
