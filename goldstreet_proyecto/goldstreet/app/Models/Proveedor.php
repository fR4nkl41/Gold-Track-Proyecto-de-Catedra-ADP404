<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    public $timestamps = false;
    protected $fillable = ['nombre', 'contacto', 'telefono', 'correo', 'direccion'];

    public function productos() { return $this->hasMany(Producto::class); }
    public function facturas()  { return $this->hasMany(Factura::class); }
}
