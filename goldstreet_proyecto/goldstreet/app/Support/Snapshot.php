<?php

namespace App\Support;

use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\MovimientoInventario;
use App\Models\PagoFactura;
use App\Models\Produccion;
use App\Models\ProduccionResultado;
use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * Snapshot: normaliza el estado del inventario y del kardex a los arreglos
 * JSON exactos que consume el frontend (dashboard, inventario, movimientos).
 */
class Snapshot
{
    public static function producto(Producto $p): array
    {
        return [
            'id'           => $p->id,
            'codigo'       => $p->codigo,
            'nombre'       => $p->nombre,
            'unidad'       => $p->unidad,
            'stock'        => (float) $p->stock,
            'stock_minimo' => (float) $p->stock_minimo,
            'stock_maximo' => $p->stock_maximo !== null ? (float) $p->stock_maximo : null,
            'es_preparado' => (bool) $p->es_preparado,   // porción hecha en cocina (no se compra)
            'costo'        => (float) $p->costo,
            'estado'       => $p->estado,
            'estado_stock' => $p->estado_stock,   // optimo | bajo | agotado (RF06)
            'valor'        => round($p->stock * $p->costo, 2),
            'categoria'    => $p->categoria?->nombre,
            'categoria_id' => $p->categoria_id,
            'proveedor'    => $p->proveedor?->nombre,
            'proveedor_id' => $p->proveedor_id,
        ];
    }

    public static function productos(Collection $productos): array
    {
        return $productos->map(fn ($p) => self::producto($p))->values()->all();
    }

    public static function movimiento(MovimientoInventario $m): array
    {
        return [
            'id'         => $m->id,
            'fecha'      => $m->created_at?->format('d/m/Y H:i'),
            'tipo'       => $m->tipo,
            'es_merma'   => $m->es_merma,                       // salida con causa = pérdida
            'clase'      => $m->clase,                          // compra · entrada · consumo · merma · produccion · anulacion
            'causa'      => $m->causa ? MovimientoInventario::CAUSAS[$m->causa] : null,
            'producto'   => $m->producto?->nombre,
            'cantidad'   => (float) $m->cantidad,
            'unidad'     => $m->producto?->unidad,
            'usuario'    => $m->usuario?->nombre,
            'referencia' => $m->referencia,
        ];
    }

    public static function movimientos(Collection $movimientos): array
    {
        return $movimientos->map(fn ($m) => self::movimiento($m))->values()->all();
    }

    /**
     * Factura de compra. Los precios y totales solo van si $conPrecios (rol admin):
     * bodega registra la factura pero no consulta precios de compra.
     */
    public static function factura(Factura $f, bool $conPrecios, bool $conDetalle = false): array
    {
        $datos = [
            'id'           => $f->id,
            'numero'       => $f->numero,
            'fecha'        => $f->fecha?->format('d/m/Y'),
            'fecha_iso'    => $f->fecha?->toDateString(),
            'proveedor'    => $f->proveedor?->nombre,
            'proveedor_id' => $f->proveedor_id,
            'usuario'      => $f->usuario?->nombre,
            'estado'       => $f->estado,
            'lineas'       => $f->detalles_count ?? $f->detalles?->count(),
            'total'        => $conPrecios ? (float) $f->total : null,
            'registrada'   => $f->created_at?->format('d/m/Y H:i'),
            'condicion'    => $f->condicion ?? 'contado',
            'vence'        => $f->fecha_vencimiento?->format('d/m/Y'),
            'orden'        => $f->orden_compra_id ? 'OC-'.str_pad((string) $f->orden_compra_id, 5, '0', STR_PAD_LEFT) : null,
        ];

        // Cuentas por pagar (solo admin)
        if ($conPrecios) {
            $datos += [
                'pagado'      => $f->pagado,
                'saldo'       => $f->saldo,
                'estado_pago' => $f->estado_pago,
            ];
        }

        if ($f->estado === 'anulada') {
            $datos['anulacion'] = [
                'motivo' => $f->motivo_anulacion,
                'por'    => $f->anuladaPor?->nombre,
                'fecha'  => $f->anulada_at?->format('d/m/Y H:i'),
            ];
        }

        if ($conDetalle && $conPrecios) {
            $datos['pagos'] = $f->pagos->sortBy('fecha')->map(fn (PagoFactura $p) => [
                'fecha'      => $p->fecha?->format('d/m/Y'),
                'monto'      => $p->monto,
                'metodo'     => PagoFactura::METODOS[$p->metodo] ?? $p->metodo,
                'referencia' => $p->referencia,
                'usuario'    => $p->usuario?->nombre,
            ])->values()->all();
        }

        if ($conDetalle) {
            $datos['detalles'] = $f->detalles->map(fn (FacturaDetalle $d) => [
                'producto_id'     => $d->producto_id,
                'codigo'          => $d->producto?->codigo,
                'producto'        => $d->producto?->nombre,
                'unidad'          => $d->producto?->unidad,
                'cantidad'        => $d->cantidad,
                'precio_unitario' => $conPrecios ? $d->precio_unitario : null,
                'subtotal'        => $conPrecios ? $d->subtotal : null,
            ])->values()->all();
        }

        return $datos;
    }

    public static function produccion(Produccion $p): array
    {
        $origen = $p->origen;

        return [
            'id'              => $p->id,
            'fecha'           => $p->created_at?->format('d/m/Y H:i'),
            'usuario'         => $p->usuario?->nombre,
            'origen'          => $origen?->nombre,
            'origen_id'       => $p->producto_origen_id,
            'unidad_origen'   => $origen?->unidad,
            'cantidad_origen' => $p->cantidad_origen,
            'desperdicio'     => $p->desperdicio,
            'aprovechado'     => round($p->cantidad_origen - $p->desperdicio, 2),
            'aprovechamiento' => $p->aprovechamiento,
            'notas'           => $p->notas,
            'resultados'      => $p->resultados->map(fn (ProduccionResultado $r) => [
                'producto_id' => $r->producto_id,
                'producto'    => $r->producto?->nombre,
                'unidad'      => $r->producto?->unidad,
                'cantidad'    => $r->cantidad,
            ])->values()->all(),
        ];
    }
}
