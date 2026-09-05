<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ZonaHorariaRequest;
use App\Models\Comercio;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ZonaHorariaController extends Controller
{
    public function edit(): View
    {
        return view('zona-horaria.edit', [
            'opciones' => Comercio::ZONAS_HORARIAS,
            'actual' => tenant('timezone'),
        ]);
    }

    public function update(ZonaHorariaRequest $request): RedirectResponse
    {
        $comercio = tenant();
        $comercio->timezone = $request->validated('timezone');
        $comercio->save();

        return redirect()->route('panel')->with('status', 'Zona horaria configurada correctamente.');
    }
}
