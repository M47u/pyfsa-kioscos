<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
     * Etiquetas legibles para la vista — separadas del valor guardado a
     * propósito: cambiar el texto de la pantalla no debe reescribir el
     * historial ya persistido.
     */
    public const ETIQUETAS = [
        self::ACCION_COMERCIO_CREADO => 'Comercio creado',
        self::ACCION_COMERCIO_ESTADO_ACTUALIZADO => 'Estado de suscripción actualizado',
    ];

    protected $fillable = [
        'user_id',
        'accion',
        'comercio_id',
        'detalles',
    ];

    protected function casts(): array
    {
        return [
            'detalles' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Punto de entrada único desde los controllers — resuelve solo el
     * "quién" (el admin autenticado) para que ningún call site se olvide
     * de pasarlo. `user_id` queda nullable porque esto puede correr sin
     * sesión (un comando artisan futuro, por ejemplo).
     *
     * @param  array<string, mixed>  $detalles
     */
    public static function registrar(string $accion, ?string $comercioId = null, array $detalles = []): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'comercio_id' => $comercioId,
            'detalles' => $detalles === [] ? null : $detalles,
        ]);
    }

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->accion] ?? $this->accion;
    }
}
