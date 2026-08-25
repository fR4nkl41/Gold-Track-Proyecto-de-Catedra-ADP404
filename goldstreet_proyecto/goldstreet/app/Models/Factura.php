<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Factura de compra a un proveedor. Al registrarla, cada línea genera una ENTRADA. */
class Factura extends Model
{
    protected $table = 'facturas';
    public $timestamps = false;

    protected $fillable = [
        'proveedor_id', 'numero', 'fecha', 'condicion', 'fecha_vencimiento', 'orden_compra_id', 'usuario_id', 'total',
        'estado', 'motivo_anulacion', 'anulada_por', 'anulada_at',
    ];

    protected $casts = [
        'fecha'      => 'date',
        'fecha_vencimiento' => 'date',
        'total'      => 'float',
        'anulada_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $f) => $f->created_at ??= now());
    }

    public function proveedor()   { return $this->belongsTo(Proveedor::class); }
    public function usuario()     { return $this->belongsTo(Usuario::class); }
    public function anuladaPor()  { return $this->belongsTo(Usuario::class, 'anulada_por'); }
    public function detalles()    { return $this->hasMany(FacturaDetalle::class); }
    public function movimientos() { return $this->hasMany(MovimientoInventario::class); }
    public function pagos()       { return $this->hasMany(PagoFactura::class); }
    public function orden()       { return $this->belongsTo(OrdenCompra::class, 'orden_compra_id'); }

    // ---- Cuentas por pagar: una factura de contado se pagó al recibirla ----------

    public function getPagadoAttribute(): float
    {
        return $this->condicion === 'contado' ? $this->total : round((float) $this->pagos->sum('monto'), 2);
    }

    public function getSaldoAttribute(): float
    {
        return $this->estado === 'anulada' ? 0.0 : round(max(0, $this->total - $this->pagado), 2);
    }

    /** contado · pagada · pendiente · vencida · anulada */
    public function getEstadoPagoAttribute(): string
    {
        return match (true) {
            $this->estado === 'anulada'     => 'anulada',
            $this->condicion === 'contado'  => 'contado',
            $this->saldo <= 0               => 'pagada',
            $this->fecha_vencimiento && $this->fecha_vencimiento->lt(today()) => 'vencida',
            default                         => 'pendiente',
        };
    }
}
