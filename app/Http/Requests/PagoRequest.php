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
            // max:99999999.99 es el tope real de la columna pagos.monto
            // (decimal(10,2)): sin este límite, un monto de 9 dígitos pasa
            // la validación y explota como un error crudo de MySQL al
            // insertar.
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            // Offline (ver VentaRequest::rules() por el mismo criterio y
            // CLAUDE.md, arquitectura offline): lo genera siempre el
            // frontend, sin 'unique' a propósito — un uuid repetido es un
            // sync repetido, no un error (ver ClienteController::registrarPago).
            'uuid_dispositivo' => ['nullable', 'string', 'uuid'],
        ];
    }
}
