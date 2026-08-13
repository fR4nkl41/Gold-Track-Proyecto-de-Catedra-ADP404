<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::where('correo', $request->correo)->first();

        if (! $usuario || ! Hash::check($request->contrasena, $usuario->contrasena)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => 'El usuario está inactivo. Contacta al administrador.'], 403);
        }

        // Una sesión activa por usuario: evita acumulación de tokens viejos
        $usuario->tokens()->delete();
        $token = $usuario->createToken('goldstreet-frontend')->plainTextToken;

        return response()->json([
            'token'   => $token,
            'usuario' => [
                'id'     => $usuario->id,
                'nombre' => $usuario->nombre,
                'correo' => $usuario->correo,
                'rol'    => $usuario->rol,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'usuario' => [
                'id' => $u->id, 'nombre' => $u->nombre, 'correo' => $u->correo, 'rol' => $u->rol,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
