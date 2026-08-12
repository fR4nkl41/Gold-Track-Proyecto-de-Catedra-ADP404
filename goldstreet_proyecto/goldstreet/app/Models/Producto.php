<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    /** unidad_medida (diccionario de datos 3.4) */
    public const UNIDADES = ['kg', 'lb', 'L', 'und', 'caja'];

    // 'stock' no es asignable: solo lo escribe el trigger trg_mov_stock (sección 4.2)
    protected $fillable = [
        'codigo', 'nombre', 'categoria_id', 'proveedor_id',
        'unidad', 'stock_minimo', 'stock_maximo', 'es_preparado', 'costo', 'estado',
    ];

    protected $casts = [
        'created_at'   => 'datetime',
        'stock'        => 'float',
        'stock_minimo' => 'float',
        'stock_maximo' => 'float',
        'es_preparado' => 'boolean',
        'costo'        => 'float',
    ];

    public function categoria()   { return $this->belongsTo(Categoria::class); }
    public function proveedor()   { return $this->belongsTo(Proveedor::class); }
    public function movimientos() { return $this->hasMany(MovimientoInventario::class); }

    /**
     * Nivel al que se repone en la lista de compras sugerida: el stock ideal (máximo)
     * si se definió; si no, el doble del mínimo.
     */
    public function getStockObjetivoAttribute(): float
    {
        return (float) ($this->stock_maximo ?: $this->stock_minimo * 2);
    }

    /**
     * RF06: estado del stock en 3 niveles (óptimo / bajo / agotado).
     * RF05: la alerta salta cuando el stock es IGUAL o MENOR al mínimo.
     */
    public function getEstadoStockAttribute(): string
    {
        if ((float) $this->stock <= 0) {
            return 'agotado';
        }
        if ((float) $this->stock <= (float) $this->stock_minimo) {
            return 'bajo';
        }
        return 'optimo';
    }
}
