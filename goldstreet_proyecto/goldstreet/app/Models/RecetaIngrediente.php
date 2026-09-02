<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecetaIngrediente extends Model
{
    protected $table = 'receta_ingredientes';
    public $timestamps = false;

    protected $fillable = ['plato_id', 'producto_id', 'cantidad'];

    protected $casts = ['cantidad' => 'float'];

    public function plato()    { return $this->belongsTo(Plato::class); }
    public function producto() { return $this->belongsTo(Producto::class); }
}
