<?php

namespace App\Http\Controllers;

use App\Models\FacturaDetalle;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Órdenes de compra (fase 3, admin y bodega):
 *   1. Lista de compras sugerida: productos en o bajo su mínimo, agrupados por proveedor,
 *      con la cantidad para volver al stock ideal (descontando lo que ya viene en camino).
 *   2. Se genera una orden por proveedor y se imprime o se dicta al proveedor.
 *   3. Al llegar el pedido se registra su factura "recibiendo" la orden: queda lo pedido vs. lo recibido.
 * Los precios estimados solo los ve el admin (igual que en las facturas).
 */
class OrdenCompraController extends Controller
{
    private function verPrecios(Request $request): bool
    {
        return $request->user()->rol === 'admin';
    }

    public function sugerencia(Request $request): JsonResponse
    {
        // Lo que ya está pedido en órdenes pendientes ("en camino")
        $enCamino = OrdenCompraDetalle::whereHas('orden', fn ($q) => $q->where('estado', 'pendiente'))
            ->selectRaw('producto_id, SUM(cantidad) as cantidad')
            ->groupBy('producto_id')
            ->pluck('cantidad', 'producto_id');

        $bajos = Producto::with('proveedor')
            ->where('estado', 'activo')
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->orderBy('nombre')
            ->get();

        // Las porciones hechas en cocina no se compran: se avisan aparte para producirlas
        [$preparados, $productos] = $bajos->partition(fn (Producto $p) => $p->es_preparado);

        $precios = $this->verPrecios($request);

        $filas = $productos->map(function (Producto $p) use ($enCamino) {
            $camino = (float) ($enCamino[$p->id] ?? 0);
            $falta = $p->stock_objetivo - (float) $p->stock - $camino;
            // Unidades enteras para lo que se compra por pieza o caja
            $sugerido = in_array($p->unidad, ['und', 'caja'], true) ? ceil($falta) : round($falta, 2);

            return [
                'producto_id'  => $p->id,
                'codigo'       => $p->codigo,
                'producto'     => $p->nombre,
                'unidad'       => $p->unidad,
                'stock'        => (float) $p->stock,
                'stock_minimo' => (float) $p->stock_minimo,
                'objetivo'     => $p->stock_objetivo,
                'en_camino'    => $camino,
                'sugerido'     => max(0, $sugerido),
                'estado_stock' => $p->estado_stock,
                'proveedor_id' => $p->proveedor_id,
                'proveedor'    => $p->proveedor?->nombre,
                'precio'       => $this->ultimoPrecio($p),
            ];
        })->filter(fn ($f) => $f['sugerido'] > 0);

        $grupos = $filas->groupBy('proveedor_id')->map(fn ($g) => [
            'proveedor_id' => $g->first()['proveedor_id'],
            'proveedor'    => $g->first()['proveedor'],
            'total'        => $precios ? round($g->sum(fn ($f) => $f['sugerido'] * $f['precio']), 2) : null,
            'productos'    => $g->map(fn ($f) => $precios ? $f : array_merge($f, ['precio' => null]))->values(),
        ])->sortBy('proveedor')->values();

        return response()->json([
            'data'    => $grupos,
            'porPreparar' => $preparados->map(fn (Producto $p) => [
                'producto_id' => $p->id,
                'producto'    => $p->nombre,
                'unidad'      => $p->unidad,
                'stock'       => (float) $p->stock,
                'sugerido'    => max(0, ceil($p->stock_objetivo - (float) $p->stock)),
            ])->values(),
            'totales' => [
                'productos'   => $filas->count(),
                'proveedores' => $grupos->count(),
                'estimado'    => $precios ? round($filas->sum(fn ($f) => $f['sugerido'] * $f['precio']), 2) : null,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'estado'       => ['nullable', 'in:pendiente,recibida,cancelada'],
            'proveedor_id' => ['nullable', 'integer'],
        ]);

        $ordenes = OrdenCompra::with(['proveedor', 'usuario', 'factura'])
            ->withCount('detalles')
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $precios = $this->verPrecios($request);

        return response()->json(['data' => $ordenes->map(fn ($o) => $this->snapshot($o, $precios))->values()]);
    }

    public function show(Request $request, OrdenCompra $orden): JsonResponse
    {
        $orden->load(['proveedor', 'usuario', 'factura', 'detalles.producto']);

        return response()->json(['data' => $this->snapshot($orden, $this->verPrecios($request), true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'proveedor_id'             => ['required', 'integer', 'exists:proveedores,id'],
            'fecha_entrega'            => ['nullable', 'date', 'after_or_equal:today'],
            'notas'                    => ['nullable', 'string', 'max:255'],
            'lineas'                   => ['required', 'array', 'min:1', 'max:60'],
            'lineas.*.producto_id'     => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'lineas.*.cantidad'        => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'lineas.*.precio_estimado' => ['nullable', 'numeric', 'min:0'],
        ], [
            'proveedor_id.required'          => 'Selecciona el proveedor de la orden.',
            'fecha_entrega.after_or_equal'   => 'La fecha de entrega no puede ser pasada.',
            'lineas.required'                => 'La orden necesita al menos un producto.',
            'lineas.min'                     => 'La orden necesita al menos un producto.',
            'lineas.*.producto_id.distinct'  => 'Un producto aparece dos veces en la orden.',
            'lineas.*.cantidad.min'          => 'La cantidad de cada producto debe ser mayor que cero.',
        ]);

        $precios = $this->verPrecios($request);

        $orden = DB::transaction(function () use ($request, $precios) {
            $productos = Producto::whereIn('id', collect($request->lineas)->pluck('producto_id'))->get()->keyBy('id');

            $orden = OrdenCompra::create([
                'proveedor_id'  => $request->proveedor_id,
                'usuario_id'    => $request->user()->id,
                'fecha_entrega' => $request->fecha_entrega,
                'notas'         => $request->notas,
            ]);

            $total = 0;
            foreach ($request->lineas as $l) {
                // Bodega no maneja precios: se estima con el último precio de compra
                $precio = $precios && isset($l['precio_estimado'])
                    ? (float) $l['precio_estimado']
                    : $this->ultimoPrecio($productos[$l['producto_id']]);
                $total += $l['cantidad'] * $precio;

                OrdenCompraDetalle::create([
                    'orden_compra_id' => $orden->id,
                    'producto_id'     => $l['producto_id'],
                    'cantidad'        => $l['cantidad'],
                    'precio_estimado' => $precio,
                ]);
            }
            $orden->update(['total_estimado' => round($total, 2)]);

            return $orden;
        });

        $orden->load(['proveedor', 'usuario', 'detalles.producto']);

        return response()->json([
            'message' => "Orden {$orden->numero} creada para {$orden->proveedor->nombre}.",
            'data'    => $this->snapshot($orden, $precios, true),
        ], 201);
    }

    public function cancelar(OrdenCompra $orden): JsonResponse
    {
        if ($orden->estado !== 'pendiente') {
            return response()->json(['message' => "La orden {$orden->numero} ya está {$orden->estado}: no se puede cancelar."], 409);
        }

        $orden->update(['estado' => 'cancelada']);

        return response()->json(['message' => "Orden {$orden->numero} cancelada."]);
    }

    /** Último precio pagado por el producto (de las facturas); si nunca se facturó, su costo. */
    private function ultimoPrecio(Producto $p): float
    {
        $precio = FacturaDetalle::join('facturas', 'facturas.id', '=', 'factura_detalles.factura_id')
            ->where('factura_detalles.producto_id', $p->id)
            ->where('facturas.estado', 'registrada')
            ->orderByDesc('facturas.fecha')
            ->orderByDesc('factura_detalles.id')
            ->value('factura_detalles.precio_unitario');

        return (float) ($precio ?? $p->costo);
    }

    private function snapshot(OrdenCompra $o, bool $precios, bool $detalle = false): array
    {
        $datos = [
            'id'            => $o->id,
            'numero'        => $o->numero,
            'proveedor'     => $o->proveedor?->nombre,
            'proveedor_id'  => $o->proveedor_id,
            'estado'        => $o->estado,
            'fecha'         => $o->created_at?->format('d/m/Y'),
            'fecha_entrega' => $o->fecha_entrega?->format('d/m/Y'),
            'notas'         => $o->notas,
            'usuario'       => $o->usuario?->nombre,
            'lineas'        => $o->detalles_count ?? $o->detalles->count(),
            'total'         => $precios ? $o->total_estimado : null,
            'factura'       => $o->factura ? ['id' => $o->factura->id, 'numero' => $o->factura->numero] : null,
        ];

        if ($detalle) {
            $datos['detalles'] = $o->detalles->map(fn (OrdenCompraDetalle $d) => [
                'producto_id'       => $d->producto_id,
                'codigo'            => $d->producto?->codigo,
                'producto'          => $d->producto?->nombre,
                'unidad'            => $d->producto?->unidad,
                'cantidad'          => $d->cantidad,
                'precio_estimado'   => $precios ? $d->precio_estimado : null,
                'subtotal'          => $precios ? round($d->cantidad * $d->precio_estimado, 2) : null,
                'cantidad_recibida' => $d->cantidad_recibida,
                'diferencia'        => $d->cantidad_recibida !== null ? round($d->cantidad_recibida - $d->cantidad, 2) : null,
            ])->values()->all();
        }

        return $datos;
    }
}
