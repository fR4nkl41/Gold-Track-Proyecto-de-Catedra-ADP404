<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduccionResultado extends Model
{
    protected $table = 'produccion_resultados';
    public $timestamps = false;

    protected $fillable = ['produccion_id', 'producto_id', 'cantidad'];

    protected $casts = ['cantidad' => 'float'];

    public function produccion() { return $this->belongsTo(Produccion::class); }
    public function producto()   { return $this->belongsTo(Producto::class); }
}
