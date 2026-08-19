<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $producto = $this->route('producto');

        return [
            'codigo'       => ['required', 'string', 'max:30', Rule::unique('productos', 'codigo')->ignore($producto?->id)],
            'nombre'       => ['required', 'string', 'max:120'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'unidad'       => ['required', Rule::in(Producto::UNIDADES)],
            // 'stock' NO se edita aquí: solo cambia vía movimientos (trigger, sección 4.2)
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
            'codigo.unique' => 'Ya existe otro producto con ese código.',
            'nombre.required' => 'El nombre es obligatorio.',
            'categoria_id.required' => 'Selecciona una categoría.',
            'proveedor_id.required' => 'Selecciona un proveedor.',
            'unidad.in' => 'La unidad debe ser kg, lb, L, und o caja.',
            'stock_maximo.gt' => 'El stock ideal debe ser mayor que el mínimo.',
        ];
    }
}
