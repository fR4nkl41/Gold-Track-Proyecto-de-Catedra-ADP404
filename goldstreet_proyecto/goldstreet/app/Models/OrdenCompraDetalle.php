<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraDetalle extends Model
{
    protected $table = 'orden_compra_detalles';
    public $timestamps = false;

    protected $fillable = ['orden_compra_id', 'producto_id', 'cantidad', 'precio_estimado', 'cantidad_recibida'];

    protected $casts = [
        'cantidad'          => 'float',
        'precio_estimado'   => 'float',
        'cantidad_recibida' => 'float',
    ];

    public function orden()    { return $this->belongsTo(OrdenCompra::class, 'orden_compra_id'); }
    public function producto() { return $this->belongsTo(Producto::class); }
}
