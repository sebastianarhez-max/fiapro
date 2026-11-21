<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige sesión iniciada, usuario activo y tienda activa. Si se indican roles, exige uno de ellos.
 * Uso: RolMiddleware::class.':admin_tienda,cliente'
 */
class RolMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/ingresar');
        }

        $tiendaInactiva = $user->tienda_id
            && DB::table('tienda')->where('tienda_id', $user->tienda_id)->value('estado') !== 'activo';

        if ($user->estado !== 'activo' || $tiendaInactiva) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/ingresar')->withErrors(['correo' => 'Tu usuario o tu tienda está dado de baja.']);
        }

        if ($roles && ! in_array($user->rol, $roles, true)) {
            abort(403, 'No tienes permiso para ver esta pantalla.');
        }

        return $next($request);
    }
}
