<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function formulario()
    {
        if (Auth::check()) {
            return redirect('/inicio');
        }

        return view('ingresar');
    }

    public function ingresar(Request $request)
    {
        $datos = $request->validate([
            'correo' => 'required|email',
            'password' => 'required|string',
        ]);

        $clave = Str::lower($datos['correo']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $seg = RateLimiter::availableIn($clave);

            return back()->withErrors(['correo' => "Demasiados intentos. Intenta de nuevo en {$seg} segundos."])->onlyInput('correo');
        }

        $ok = Auth::attempt(
            ['email' => $datos['correo'], 'password' => $datos['password'], 'estado' => 'activo'],
            $request->boolean('recordar')
        );

        if (! $ok) {
            RateLimiter::hit($clave, 60);
            Bitacora::registrar('login_fallido', 'auth', null, ['correo' => $datos['correo']]);

            return back()->withErrors(['correo' => 'Correo o contraseña incorrectos, o usuario dado de baja.'])->onlyInput('correo');
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();
        Bitacora::registrar('login', 'auth');

        return redirect('/inicio');
    }

    public function salir(Request $request)
    {
        Bitacora::registrar('logout', 'auth');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/ingresar');
    }

    /** Lleva a cada usuario a su pantalla según el rol. */
    public function inicio()
    {
        return redirect(match (Auth::user()->rol) {
            'admin_sistema' => '/admin/tiendas',
            'admin_tienda' => '/panel',
            default => '/mi-cuenta',
        });
    }
}
