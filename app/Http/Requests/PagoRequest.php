<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de un pago contra la cuenta corriente (fiado) de un Cliente.
 * No valida contra el saldo actual: un pago puede ser parcial, total, o
 * incluso mayor al saldo (deja saldo negativo, ver Cliente::saldo()) — no
 * hay ninguna regla de negocio que lo prohíba.
 */
class PagoRequest extends FormRequest
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
            'monto' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
