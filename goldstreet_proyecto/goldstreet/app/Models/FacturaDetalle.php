<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacturaDetalle extends Model
{
    protected $table = 'factura_detalles';
    public $timestamps = false;

    protected $fillable = ['factura_id', 'producto_id', 'cantidad', 'precio_unitario', 'subtotal'];

    protected $casts = [
        'cantidad'        => 'float',
        'precio_unitario' => 'float',
        'subtotal'        => 'float',
    ];

    public function factura()  { return $this->belongsTo(Factura::class); }
    public function producto() { return $this->belongsTo(Producto::class); }
}
