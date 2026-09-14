<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la anulación de una venta. El motivo es opcional a
 * propósito (documento de alcance — corrección de error humano): no
 * queremos friccionar corregir un error con un campo obligatorio.
 * La autorización (solo dueño) NO va acá: la resuelve EnsureUserIsDueno
 * en routes/tenant.php, mismo criterio que el resto de los FormRequest
 * de esta app (ver PagoRequest, ReponerStockRequest).
 */
class AnularVentaRequest extends FormRequest
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
            'motivo_anulacion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
