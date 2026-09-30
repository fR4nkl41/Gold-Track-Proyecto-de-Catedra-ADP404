<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/** Gestión de usuarios y roles (solo admin). No hay borrado: se desactivan. */
class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::orderBy('nombre')->get()->map(fn ($u) => $this->fila($u));

        return response()->json(['data' => $usuarios, 'total' => $usuarios->count()]);
    }

    public function store(UsuarioRequest $request): JsonResponse
    {
        $datos = $request->safe()->except('contrasena');
        $datos['contrasena'] = Hash::make($request->contrasena);

        $usuario = Usuario::create($datos);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data'    => $this->fila($usuario->fresh()),
        ], 201);
    }

    public function update(UsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        // Evita que el admin se bloquee a sí mismo (quitarse el rol o desactivarse)
        if ($usuario->id === $request->user()->id
            && ($request->rol !== 'admin' || $request->boolean('activo', true) === false)) {
            return response()->json([
                'message' => 'No puedes quitarte el rol de administrador ni desactivar tu propia cuenta.',
            ], 422);
        }

        $datos = $request->safe()->except('contrasena');
        if ($request->filled('contrasena')) {
            $datos['contrasena'] = Hash::make($request->contrasena);
        }

        $usuario->update($datos);

        // Si se desactiva, se cierran sus sesiones abiertas
        if (! $usuario->activo) {
            $usuario->tokens()->delete();
        }

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data'    => $this->fila($usuario),
        ]);
    }

    private function fila(Usuario $u): array
    {
        return [
            'id'      => $u->id,
            'nombre'  => $u->nombre,
            'correo'  => $u->correo,
            'rol'     => $u->rol,
            'activo'  => (bool) $u->activo,
            'creado'  => $u->created_at?->format('d/m/Y'),
        ];
    }
}
