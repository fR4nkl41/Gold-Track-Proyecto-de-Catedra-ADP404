<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Uso: ->middleware('role:admin')  o  ->middleware('role:admin,bodega')
 * Devuelve 403 JSON si el rol del usuario no está en la lista.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! $request->user() || ! in_array($request->user()->rol, $roles, true)) {
            return response()->json([
                'message' => 'Acceso denegado: tu rol no puede realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
