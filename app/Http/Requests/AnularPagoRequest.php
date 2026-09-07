<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la anulación de un pago. Ver AnularVentaRequest — mismo
 * criterio (motivo opcional, autorización resuelta por EnsureUserIsDueno).
 */
class AnularPagoRequest extends FormRequest
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
