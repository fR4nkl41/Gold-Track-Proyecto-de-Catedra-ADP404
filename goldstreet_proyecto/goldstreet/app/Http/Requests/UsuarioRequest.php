<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta (POST) y edición (PUT) de usuarios. En edición la contraseña es opcional. */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $creando = $this->isMethod('post');

        return [
            'nombre'     => ['required', 'string', 'max:100'],
            'correo'     => ['required', 'email', 'max:120', Rule::unique('usuarios', 'correo')->ignore($usuario?->id)],
            'contrasena' => [$creando ? 'required' : 'nullable', 'string', 'min:6'],
            'rol'        => ['required', 'in:admin,bodega,cocina'],
            'activo'     => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'     => 'El nombre es obligatorio.',
            'correo.required'     => 'El correo es obligatorio.',
            'correo.email'        => 'El correo no tiene un formato válido.',
            'correo.unique'       => 'Ya existe un usuario con ese correo.',
            'contrasena.required' => 'La contraseña es obligatoria.',
            'contrasena.min'      => 'La contraseña debe tener al menos 6 caracteres.',
            'rol.in'              => 'El rol debe ser admin, bodega o cocina.',
        ];
    }
}
