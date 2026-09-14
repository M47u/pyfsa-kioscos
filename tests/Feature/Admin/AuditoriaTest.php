<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Comercio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ProvisionaTenantConBaseReal;
use Tests\TestCase;

/**
 * Auditoría de las acciones admin (ver RegistroAuditoria y
 * Admin\AuditoriaController). Mismo encuadre que Admin\ComercioTest: rutas
 * CENTRALES, sin tenancy pre-inicializada, así que no hereda de
 * TenantTestCase.
 */
class AuditoriaTest extends TestCase
{
    use ProvisionaTenantConBaseReal;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Comercio::all()->each->delete();
        $this->borrarComerciosConBaseReal();

        parent::tearDown();
    }

    public function test_usuario_sin_is_admin_no_puede_ver_la_auditoria(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.auditoria.index'))->assertForbidden();
    }

    public function test_usuario_admin_puede_ver_la_auditoria(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.auditoria.index'))->assertOk();
    }

    public function test_crear_un_comercio_deja_un_registro_de_auditoria(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $nombreBase = $this->crearBaseDeDatosSuelta();

        $this->actingAs($admin)->post(route('admin.comercios.store'), [
            'nombre_comercio' => 'Kiosco Auditado',
            'nombre_base' => $nombreBase,
            'name' => 'Dueño Auditado',
            'email' => 'auditado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.comercios.index'));

        $comercio = Comercio::all()->firstWhere('nombre', 'Kiosco Auditado');
        $registro = RegistroAuditoria::sole();

        $this->assertSame(RegistroAuditoria::ACCION_COMERCIO_CREADO, $registro->accion);
        $this->assertSame($admin->id, $registro->user_id);
        $this->assertSame($comercio->id, $registro->comercio_id);
        $this->assertSame('Kiosco Auditado', $registro->detalles['nombre_comercio']);
        $this->assertSame($nombreBase, $registro->detalles['nombre_base']);
        $this->assertSame('auditado@example.com', $registro->detalles['email_dueno']);
    }

    /**
     * El detalle del cambio es el punto entero de auditar esta acción: sin
     * el estado anterior, "quedó en vencida" no dice si alguien la dio de
     * baja o si ya estaba así.
     */
    public function test_cambiar_el_estado_deja_un_registro_con_el_detalle_del_cambio(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = $this->crearComercioConBaseReal();

        $this->actingAs($admin)->put(route('admin.comercios.update', $comercio), [
            'estado_suscripcion' => Comercio::ESTADO_VENCIDA,
            'trial_termina_el' => null,
        ])->assertRedirect(route('admin.comercios.index'));

        $registro = RegistroAuditoria::sole();

        $this->assertSame(RegistroAuditoria::ACCION_COMERCIO_ESTADO_ACTUALIZADO, $registro->accion);
        $this->assertSame($admin->id, $registro->user_id);
        $this->assertSame($comercio->id, $registro->comercio_id);
        $this->assertSame(Comercio::ESTADO_PRUEBA, $registro->detalles['estado_anterior']);
        $this->assertSame(Comercio::ESTADO_VENCIDA, $registro->detalles['estado_nuevo']);
    }

    /**
     * El formulario persiste DOS campos (ComercioEstadoRequest valida
     * `estado_suscripcion` y `trial_termina_el`). Extender un trial sin
     * tocar el estado es una acción real y frecuente — "te doy dos semanas
     * más" —, y con solo el estado auditado la fila decía literalmente "de
     * prueba a prueba": un registro que afirma que no cambió nada cuando
     * sí cambió algo.
     */
    public function test_cambiar_solo_el_trial_deja_un_registro_que_refleja_ese_cambio(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comercio = $this->crearComercioConBaseReal([
            'estado_suscripcion' => Comercio::ESTADO_PRUEBA,
            'trial_termina_el' => '2026-09-20',
        ]);

        $this->actingAs($admin)->put(route('admin.comercios.update', $comercio), [
            // Mismo estado a propósito: lo único que cambia es la fecha.
            'estado_suscripcion' => Comercio::ESTADO_PRUEBA,
            'trial_termina_el' => '2026-10-04',
        ])->assertRedirect(route('admin.comercios.index'));

        $registro = RegistroAuditoria::sole();

        $this->assertSame(Comercio::ESTADO_PRUEBA, $registro->detalles['estado_anterior']);
        $this->assertSame(Comercio::ESTADO_PRUEBA, $registro->detalles['estado_nuevo']);
        $this->assertSame('2026-09-20', $registro->detalles['trial_termina_el_anterior']);
        $this->assertSame('2026-10-04', $registro->detalles['trial_termina_el_nuevo']);
    }

    /**
     * Una acción rechazada por el gate no tiene que dejar rastro de nada:
     * no pasó.
     */
    public function test_una_accion_bloqueada_por_el_gate_no_deja_registro(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $nombreBase = $this->crearBaseDeDatosSuelta();

        $this->actingAs($user)->post(route('admin.comercios.store'), [
            'nombre_comercio' => 'Kiosco Colado',
            'nombre_base' => $nombreBase,
            'name' => 'Dueño Colado',
            'email' => 'colado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->assertSame(0, RegistroAuditoria::count());
    }

    /**
     * El listado resuelve el "quién" y el "sobre qué" en PHP (no hay
     * foreign keys, ver la migración) — este test cubre que esa resolución
     * llegue de verdad a la pantalla.
     */
    public function test_el_listado_muestra_quien_hizo_cada_accion(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Auditor']);
        $comercio = $this->crearComercioConBaseReal(['nombre' => 'Kiosco Del Registro']);

        $this->actingAs($admin)->put(route('admin.comercios.update', $comercio), [
            'estado_suscripcion' => Comercio::ESTADO_ACTIVA,
            'trial_termina_el' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.auditoria.index'));

        $response->assertOk();
        $response->assertSee('Admin Auditor');
        $response->assertSee('Kiosco Del Registro');
        $response->assertSee(RegistroAuditoria::ETIQUETAS[RegistroAuditoria::ACCION_COMERCIO_ESTADO_ACTUALIZADO]);
    }

    /**
     * Regresión del "quién" cuando el admin que ejecutó la acción fue dado
     * de baja después (users usa SoftDeletes): el listado tiene que seguir
     * mostrándolo, no dejar la columna vacía.
     */
    public function test_el_listado_sigue_mostrando_a_un_admin_dado_de_baja(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Que Se Fue']);
        $comercio = $this->crearComercioConBaseReal();

        $this->actingAs($admin)->put(route('admin.comercios.update', $comercio), [
            'estado_suscripcion' => Comercio::ESTADO_ACTIVA,
            'trial_termina_el' => null,
        ]);

        $admin->delete();

        $otroAdmin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($otroAdmin)->get(route('admin.auditoria.index'));

        $response->assertOk();
        $response->assertSee('Admin Que Se Fue');
    }
}
