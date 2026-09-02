<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Plato del menú con su receta. El costo NO se guarda: se calcula con el costo actual
 * de cada ingrediente, así cuando sube el pollo (factura nueva) sube el costo del plato.
 */
class Plato extends Model
{
    protected $table = 'platos';
    public $timestamps = false;

    protected $fillable = ['nombre', 'categoria', 'precio_venta', 'descripcion', 'activo'];

    protected $casts = [
        'precio_venta' => 'float',
        'activo'       => 'boolean',
        'created_at'   => 'datetime',
    ];

    /** Food cost (% del precio que se va en ingredientes): verde ≤ 30 %, amarillo ≤ 40 %, rojo más. */
    public const FOOD_COST_IDEAL = 30;
    public const FOOD_COST_LIMITE = 40;

    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->created_at ??= now());
    }

    public function ingredientes() { return $this->hasMany(RecetaIngrediente::class); }

    public function getCostoAttribute(): float
    {
        return round((float) $this->ingredientes->sum(fn ($i) => $i->cantidad * ($i->producto?->costo ?? 0)), 2);
    }

    public function getMargenAttribute(): float
    {
        return round($this->precio_venta - $this->costo, 2);
    }

    public function getFoodCostAttribute(): float
    {
        return $this->precio_venta > 0 ? round($this->costo / $this->precio_venta * 100, 1) : 0.0;
    }

    /** Cuántos platos alcanzan con el stock actual (lo limita el ingrediente más escaso). */
    public function getPorcionesPosiblesAttribute(): int
    {
        if ($this->ingredientes->isEmpty()) {
            return 0;
        }

        return (int) $this->ingredientes
            ->map(fn ($i) => $i->cantidad > 0 ? floor(max(0, (float) ($i->producto?->stock ?? 0)) / $i->cantidad + 1e-9) : 0)
            ->min();
    }
}
