<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Producción / porcionado: una materia prima (ej. 21 lb de puyazo) se transforma en
 * porciones (41 órdenes) y deja un desperdicio (0.64 lb) que se registra como merma.
 */
class Produccion extends Model
{
    protected $table = 'producciones';
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'producto_origen_id', 'cantidad_origen', 'desperdicio', 'notas'];

    protected $casts = [
        'cantidad_origen' => 'float',
        'desperdicio'     => 'float',
        'created_at'      => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->created_at ??= now());
    }

    public function usuario()     { return $this->belongsTo(Usuario::class); }
    public function origen()      { return $this->belongsTo(Producto::class, 'producto_origen_id'); }
    public function resultados()  { return $this->hasMany(ProduccionResultado::class); }
    public function movimientos() { return $this->hasMany(MovimientoInventario::class); }

    /** % de la materia prima que se aprovechó (lo que no fue desperdicio). */
    public function getAprovechamientoAttribute(): float
    {
        return $this->cantidad_origen > 0
            ? round(($this->cantidad_origen - $this->desperdicio) / $this->cantidad_origen * 100, 1)
            : 0.0;
    }
}
