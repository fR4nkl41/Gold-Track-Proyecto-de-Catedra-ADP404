<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'correo'     => ['required', 'email'],
            'contrasena' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'correo.required'     => 'El correo es obligatorio.',
            'correo.email'        => 'El correo no tiene un formato válido.',
            'contrasena.required' => 'La contraseña es obligatoria.',
        ];
    }
}
