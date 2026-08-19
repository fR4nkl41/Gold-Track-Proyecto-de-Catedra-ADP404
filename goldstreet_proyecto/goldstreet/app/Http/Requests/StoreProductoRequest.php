<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'codigo'       => ['required', 'string', 'max:30', 'unique:productos,codigo'],
            'nombre'       => ['required', 'string', 'max:120'],
            // Diccionario de datos 3.4: categoría y proveedor NOT NULL, unidad ENUM
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'unidad'       => ['required', Rule::in(Producto::UNIDADES)],
            'stock'        => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            // Stock ideal: hasta dónde repone la lista de compras sugerida (vacío = 2 × mínimo)
            'stock_maximo' => ['nullable', 'numeric', 'gt:stock_minimo'],
            'es_preparado' => ['boolean'],
            'costo'        => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique'   => 'Ya existe un producto con ese código.',
            'nombre.required' => 'El nombre es obligatorio.',
            'categoria_id.required' => 'Selecciona una categoría.',
            'proveedor_id.required' => 'Selecciona un proveedor.',
            'unidad.required' => 'La unidad de medida es obligatoria.',
            'unidad.in'       => 'La unidad debe ser kg, lb, L, und o caja.',
            'stock.required'  => 'El stock actual es obligatorio.',
            'stock_minimo.required' => 'El stock mínimo es obligatorio (dispara la alerta).',
            'costo.required'  => 'El costo unitario es obligatorio.',
            'stock_maximo.gt' => 'El stock ideal debe ser mayor que el mínimo.',
        ];
    }
}
