<?php

namespace App\Http\Requests;

use App\Models\MovimientoInventario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovimientoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'tipo'        => ['required', 'in:entrada,salida'],
            'cantidad'    => ['required', 'numeric', 'min:0.01'],
            'referencia'  => ['nullable', 'string', 'max:120'],
            // Merma = salida con causa. Una entrada nunca lleva causa.
            'causa'       => ['nullable', Rule::in(array_keys(MovimientoInventario::CAUSAS)), 'prohibited_if:tipo,entrada'],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'Selecciona un producto.',
            'producto_id.exists'   => 'El producto seleccionado no existe.',
            'tipo.required'        => 'El tipo de movimiento es obligatorio.',
            'tipo.in'              => 'El tipo debe ser entrada o salida.',
            'cantidad.required'    => 'La cantidad es obligatoria.',
            'cantidad.min'         => 'La cantidad debe ser mayor que cero.',
            'referencia.max'       => 'La referencia admite como máximo 120 caracteres.',
            'causa.in'             => 'La causa de la merma no es válida.',
            'causa.prohibited_if'  => 'Una entrada no puede registrarse como merma.',
        ];
    }
}
