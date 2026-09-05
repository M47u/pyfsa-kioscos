<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Comercio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZonaHorariaRequest extends FormRequest
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
            'timezone' => ['required', 'string', Rule::in(array_keys(Comercio::ZONAS_HORARIAS))],
        ];
    }
}
