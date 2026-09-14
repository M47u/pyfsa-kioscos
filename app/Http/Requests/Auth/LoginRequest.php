<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Intentos fallidos permitidos por email + IP antes de bloquear, en la
     * ventana de un minuto que usa RateLimiter::hit() por default.
     *
     * 5 es el estándar de la industria (y el default de Breeze/Fortify):
     * suficiente para un kiosquero que se equivoca tipeando en el celular
     * del mostrador, y sin margen útil para fuerza bruta. Importa más de lo
     * que parece porque /login es compartido — por acá entran también los
     * admins de plataforma (is_admin, ver EnsureUserIsAdmin), cuyas
     * cuentas dan acceso al panel de TODOS los comercios.
     *
     * Límite conocido, asumido: la clave incluye la IP, así que un atacante
     * distribuido (muchas IPs contra el mismo email) sigue teniendo más
     * margen. Es el mismo trade-off que hace Laravel de fábrica — sin la
     * IP, cualquiera podría bloquear la cuenta de un comercio ajeno a
     * voluntad con seis intentos basura.
     */
    private const INTENTOS_PERMITIDOS = 5;

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Autentica al usuario contra las credenciales de la request,
     * aplicando rate limiting por email + IP para frenar fuerza bruta.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::INTENTOS_PERMITIDOS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
