<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Abono a una factura al crédito (cuentas por pagar). */
class PagoFactura extends Model
{
    protected $table = 'pagos_factura';
    public $timestamps = false;

    public const METODOS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque', 'otro' => 'Otro'];

    protected $fillable = ['factura_id', 'monto', 'fecha', 'metodo', 'referencia', 'usuario_id'];

    protected $casts = [
        'monto'      => 'float',
        'fecha'      => 'date',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $p) => $p->created_at ??= now());
    }

    public function factura() { return $this->belongsTo(Factura::class); }
    public function usuario() { return $this->belongsTo(Usuario::class); }
}
