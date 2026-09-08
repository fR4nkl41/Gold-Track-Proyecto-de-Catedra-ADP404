<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre'   => ['required', 'string', 'max:120', Rule::unique('proveedores', 'nombre')->ignore($this->route('proveedor')?->id)],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo'   => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del proveedor es obligatorio.',
            'nombre.unique'   => 'Ya existe un proveedor con ese nombre.',
            'correo.email'    => 'El correo del proveedor no tiene un formato válido.',
        ];
    }
}
