<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // rutaDeInicio() y no route('panel') pelado: un admin de plataforma
        // sin comercio asignado (el caso normal de admin:crear) se
        // estrellaba contra el 403 de InitializeTenancyByAuthenticatedUser
        // apenas entraba. Ver el docblock de User::rutaDeInicio().
        return redirect()->intended($request->user()->rutaDeInicio());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
