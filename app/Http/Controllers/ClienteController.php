<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(): View
    {
        $clientes = Cliente::query()->orderBy('nombre')->get();

        return view('clientes.index', [
            'clientes' => $clientes,
        ]);
    }

    public function create(): View
    {
        return view('clientes.create', [
            'cliente' => new Cliente(),
        ]);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        Cliente::create($request->validated());

        return redirect()->route('clientes.index')->with('status', 'Cliente creado correctamente.');
    }
}
