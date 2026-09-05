<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de reposición de stock: solo acepta una cantidad entera
 * positiva. El movimiento se registra siempre como tipo 'reposicion'.
 */
class ReponerStockRequest extends FormRequest
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
            'cantidad' => ['required', 'integer', 'min:1'],
        ];
    }
}
