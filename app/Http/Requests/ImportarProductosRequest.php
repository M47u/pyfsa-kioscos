<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solo valida que se haya subido un archivo CSV/TXT válido. La validación
 * fila por fila del contenido (nombre, precio_costo, etc.) la hace
 * ProductoImportController reusando ProductoRequest::reglas() — no es
 * responsabilidad de este FormRequest, que solo mira el archivo en sí.
 */
class ImportarProductosRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'extensions:csv,txt'],
        ];
    }
}
