<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de alta de Cliente. Nunca valida saldo: eso se calcula
 * (ver Cliente::saldo()), no se carga a mano.
 */
class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La columna `limite_credito` es NOT NULL con default 0 (ver migración
     * de clientes). Si el form llega con el campo vacío,
     * ConvertEmptyStringsToNull lo vuelve null, que la regla 'nullable'
     * deja pasar, y el insert/update explota contra la DB en vez de dar
     * un error de validación prolijo. Normalizamos acá para que el
     * default de negocio quede explícito antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('limite_credito') === null) {
            $this->merge(['limite_credito' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'limite_credito' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
