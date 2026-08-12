<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos';
    public $timestamps = false;

    /** Causas de merma: una salida con causa es una pérdida, sin causa es consumo. */
    public const CAUSAS = [
        'vencido'       => 'Vencido',
        'danado'        => 'Dañado',
        'mal_preparado' => 'Mal preparado',
        'empaque'       => 'Empaque dañado',
        'desperdicio'   => 'Desperdicio',
        'otro'          => 'Otro',
    ];

    protected $fillable = ['producto_id', 'usuario_id', 'tipo', 'cantidad', 'referencia', 'causa', 'factura_id', 'produccion_id'];

    protected $casts = [
        'created_at' => 'datetime',
        'cantidad'   => 'float',
    ];

    /**
     * La fecha la pone la app (APP_TIMEZONE) y no el reloj de la BD: así "hoy", los
     * filtros por fecha y los reportes coinciden aunque el servidor MySQL esté en UTC.
     */
    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->created_at ??= now());
    }

    public function producto()   { return $this->belongsTo(Producto::class); }
    public function usuario()    { return $this->belongsTo(Usuario::class); }
    public function factura()    { return $this->belongsTo(Factura::class); }
    public function produccion() { return $this->belongsTo(Produccion::class); }

    public function getEsMermaAttribute(): bool
    {
        return $this->tipo === 'salida' && $this->causa !== null;
    }

    /**
     * Consumo real de cocina: salida sin causa que no viene de una producción
     * (materia prima transformada en porciones) ni de anular una factura.
     * Así el reporte de consumo no cuenta dos veces la carne y sus porciones.
     */
    public function getEsConsumoAttribute(): bool
    {
        return $this->tipo === 'salida' && $this->causa === null
            && $this->produccion_id === null && $this->factura_id === null;
    }

    /** compra · entrada · consumo · merma · produccion · anulacion (para etiquetas en la UI) */
    public function getClaseAttribute(): string
    {
        return match (true) {
            $this->es_merma                                     => 'merma',
            $this->produccion_id !== null                       => 'produccion',
            $this->factura_id !== null && $this->tipo === 'entrada' => 'compra',
            $this->factura_id !== null                          => 'anulacion',
            $this->tipo === 'entrada'                           => 'entrada',
            default                                             => 'consumo',
        };
    }

    /** Filtro por "clase": entrada · salida (consumo) · merma · compra (facturas) · produccion */
    public function scopeClase($query, ?string $clase)
    {
        return match ($clase) {
            'entrada'    => $query->where('tipo', 'entrada'),
            'salida'     => $query->where('tipo', 'salida')->whereNull('causa')
                                  ->whereNull('produccion_id')->whereNull('factura_id'),
            'merma'      => $query->where('tipo', 'salida')->whereNotNull('causa'),
            'compra'     => $query->where('tipo', 'entrada')->whereNotNull('factura_id'),
            'produccion' => $query->whereNotNull('produccion_id'),
            default      => $query,
        };
    }
}
