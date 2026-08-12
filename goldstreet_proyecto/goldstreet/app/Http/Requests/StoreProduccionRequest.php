<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProduccionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'producto_origen_id'     => ['required', 'integer', 'exists:productos,id'],
            'cantidad_origen'        => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'desperdicio'            => ['nullable', 'numeric', 'min:0', 'lt:cantidad_origen'],
            'notas'                  => ['nullable', 'string', 'max:150'],
            'confirmar_rendimiento'  => ['boolean'],
            'resultados'             => ['required', 'array', 'min:1', 'max:10'],
            'resultados.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'resultados.*.cantidad'  => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_origen_id.required'    => 'Selecciona la materia prima que se usó.',
            'producto_origen_id.exists'      => 'La materia prima seleccionada no existe.',
            'cantidad_origen.required'       => 'Escribe cuánta materia prima se usó.',
            'cantidad_origen.min'            => 'La cantidad usada debe ser mayor que cero.',
            'desperdicio.min'                => 'El desperdicio no puede ser negativo.',
            'desperdicio.lt'                 => 'El desperdicio debe ser menor que la cantidad usada.',
            'notas.max'                      => 'Las notas admiten como máximo 150 caracteres.',
            'resultados.required'            => 'Indica qué salió de la producción (porciones u órdenes).',
            'resultados.min'                 => 'Indica qué salió de la producción (porciones u órdenes).',
            'resultados.*.producto_id.required' => 'Cada resultado necesita un producto.',
            'resultados.*.producto_id.distinct' => 'Un producto aparece dos veces en los resultados.',
            'resultados.*.producto_id.exists'   => 'Uno de los productos resultantes no existe.',
            'resultados.*.cantidad.required' => 'Cada resultado necesita una cantidad.',
            'resultados.*.cantidad.min'      => 'La cantidad de cada resultado debe ser mayor que cero.',
        ];
    }
}
