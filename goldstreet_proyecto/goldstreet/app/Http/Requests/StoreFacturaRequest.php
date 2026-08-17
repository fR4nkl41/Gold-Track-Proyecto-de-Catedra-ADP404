<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacturaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'proveedor_id'             => ['required', 'integer', 'exists:proveedores,id'],
            'numero'                   => ['required', 'string', 'max:40',
                Rule::unique('facturas', 'numero')->where('proveedor_id', $this->input('proveedor_id'))],
            'fecha'                    => ['required', 'date', 'before_or_equal:today'],
            // Cuentas por pagar: al crédito hay que decir cuándo vence el pago
            'condicion'                => ['nullable', 'in:contado,credito'],
            'fecha_vencimiento'        => ['nullable', 'required_if:condicion,credito', 'date', 'after_or_equal:fecha'],
            // Si la factura recibe una orden de compra
            'orden_compra_id'          => ['nullable', 'integer', 'exists:ordenes_compra,id'],
            'lineas'                   => ['required', 'array', 'min:1', 'max:60'],
            'lineas.*.producto_id'     => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'lineas.*.cantidad'        => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id.required'             => 'Selecciona el proveedor de la factura.',
            'proveedor_id.exists'               => 'El proveedor seleccionado no existe.',
            'numero.required'                   => 'Escribe el número de la factura.',
            'numero.max'                        => 'El número de factura admite como máximo 40 caracteres.',
            'numero.unique'                     => 'Esa factura ya fue registrada para este proveedor.',
            'fecha.required'                    => 'La fecha de la factura es obligatoria.',
            'fecha.before_or_equal'             => 'La fecha de la factura no puede ser futura.',
            'condicion.in'                      => 'La condición de pago debe ser contado o crédito.',
            'fecha_vencimiento.required_if'     => 'Una factura al crédito necesita la fecha en que vence el pago.',
            'fecha_vencimiento.after_or_equal'  => 'El vencimiento no puede ser anterior a la fecha de la factura.',
            'orden_compra_id.exists'            => 'La orden de compra no existe.',
            'lineas.required'                   => 'Agrega al menos un producto a la factura.',
            'lineas.min'                        => 'Agrega al menos un producto a la factura.',
            'lineas.*.producto_id.required'     => 'Cada línea necesita un producto.',
            'lineas.*.producto_id.distinct'     => 'Un producto aparece en dos líneas: súmalas en una sola.',
            'lineas.*.producto_id.exists'       => 'Uno de los productos no existe.',
            'lineas.*.cantidad.required'        => 'Cada línea necesita una cantidad.',
            'lineas.*.cantidad.min'             => 'La cantidad de cada línea debe ser mayor que cero.',
            'lineas.*.precio_unitario.required' => 'Cada línea necesita el precio unitario de la factura.',
            'lineas.*.precio_unitario.min'      => 'El precio unitario no puede ser negativo.',
        ];
    }
}
