<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Orden de compra a un proveedor. Se recibe registrando su factura (que trae lo que realmente llegó). */
class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';
    public $timestamps = false;

    protected $fillable = ['proveedor_id', 'usuario_id', 'estado', 'fecha_entrega', 'notas', 'total_estimado'];

    protected $casts = [
        'fecha_entrega'  => 'date',
        'total_estimado' => 'float',
        'created_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $o) => $o->created_at ??= now());
    }

    public function proveedor() { return $this->belongsTo(Proveedor::class); }
    public function usuario()   { return $this->belongsTo(Usuario::class); }
    public function detalles()  { return $this->hasMany(OrdenCompraDetalle::class); }
    public function factura()   { return $this->hasOne(Factura::class); }

    /** OC-00012 */
    public function getNumeroAttribute(): string
    {
        return 'OC-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
