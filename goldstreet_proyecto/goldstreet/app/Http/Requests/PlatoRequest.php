<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $plato = $this->route('plato');

        return [
            'nombre'                     => ['required', 'string', 'max:120', Rule::unique('platos', 'nombre')->ignore($plato?->id)],
            'categoria'                  => ['nullable', 'string', 'max:60'],
            'precio_venta'               => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'descripcion'                => ['nullable', 'string', 'max:255'],
            'activo'                     => ['boolean'],
            'ingredientes'               => ['required', 'array', 'min:1', 'max:40'],
            'ingredientes.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'ingredientes.*.cantidad'    => ['required', 'numeric', 'min:0.001', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'                     => 'El nombre del plato es obligatorio.',
            'nombre.unique'                       => 'Ya existe un plato con ese nombre.',
            'precio_venta.required'               => 'El precio de venta es obligatorio.',
            'precio_venta.min'                    => 'El precio de venta debe ser mayor que cero.',
            'ingredientes.required'               => 'La receta necesita al menos un ingrediente.',
            'ingredientes.min'                    => 'La receta necesita al menos un ingrediente.',
            'ingredientes.*.producto_id.required' => 'Cada ingrediente necesita un producto.',
            'ingredientes.*.producto_id.distinct' => 'Un ingrediente aparece dos veces: súmalo en una sola línea.',
            'ingredientes.*.producto_id.exists'   => 'Uno de los ingredientes no existe.',
            'ingredientes.*.cantidad.required'    => 'Cada ingrediente necesita la cantidad por plato.',
            'ingredientes.*.cantidad.min'         => 'La cantidad de cada ingrediente debe ser mayor que cero.',
        ];
    }
}
