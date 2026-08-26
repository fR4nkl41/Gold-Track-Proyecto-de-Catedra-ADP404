<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFacturaRequest;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\MovimientoInventario;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Snapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Facturas de compra (fase 2). Bodega y admin las registran; cada línea genera una
 * ENTRADA en el kardex y el trigger sube el stock. Los precios solo los consulta el admin.
 */
class FacturaController extends Controller
{
    private function verPrecios(Request $request): bool
    {
        return $request->user()->rol === 'admin';
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'proveedor_id' => ['nullable', 'integer'],
            'estado'       => ['nullable', 'in:registrada,anulada'],
            'desde'        => ['nullable', 'date'],
            'hasta'        => ['nullable', 'date', 'after_or_equal:desde'],
            'buscar'       => ['nullable', 'string', 'max:40'],
        ], [
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        $facturas = Factura::with(['proveedor', 'usuario', 'anuladaPor', 'pagos'])
            ->withCount('detalles')
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->when($request->filled('buscar'), fn ($q) => $q->where('numero', 'like', '%'.addcslashes($request->buscar, '%_\\').'%'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $precios = $this->verPrecios($request);

        return response()->json([
            'data'    => $facturas->map(fn ($f) => Snapshot::factura($f, $precios))->values(),
            'totales' => [
                'facturas' => $facturas->where('estado', 'registrada')->count(),
                'monto'    => $precios ? round((float) $facturas->where('estado', 'registrada')->sum('total'), 2) : null,
            ],
        ]);
    }

    public function show(Request $request, Factura $factura): JsonResponse
    {
        $factura->load(['proveedor', 'usuario', 'anuladaPor', 'detalles.producto', 'pagos.usuario']);

        return response()->json(['data' => Snapshot::factura($factura, $this->verPrecios($request), true)]);
    }

    public function store(StoreFacturaRequest $request): JsonResponse
    {
        $factura = DB::transaction(function () use ($request) {
            $proveedor = Proveedor::findOrFail($request->proveedor_id);
            $lineas = collect($request->lineas);

            // Recepción de una orden de compra: debe ser del mismo proveedor y seguir pendiente
            $orden = $request->filled('orden_compra_id')
                ? OrdenCompra::with('detalles')->lockForUpdate()->findOrFail($request->orden_compra_id)
                : null;
            if ($orden && ($orden->proveedor_id !== $proveedor->id || $orden->estado !== 'pendiente')) {
                throw ValidationException::withMessages([
                    'orden_compra_id' => "La orden {$orden->numero} no está pendiente o es de otro proveedor.",
                ]);
            }

            // Bloqueo de los productos de la factura (mismo criterio que los movimientos, 4.3)
            $productos = Producto::whereIn('id', $lineas->pluck('producto_id'))->lockForUpdate()->get()->keyBy('id');

            $inactivos = $productos->where('estado', '!=', 'activo')->pluck('nombre');
            if ($inactivos->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'lineas' => 'Estos productos están dados de baja: '.$inactivos->implode(', ').'.',
                ]);
            }

            $factura = Factura::create([
                'proveedor_id' => $proveedor->id,
                'numero'       => trim($request->numero),
                'fecha'        => $request->fecha,
                'condicion'    => $request->input('condicion', 'contado'),
                'fecha_vencimiento' => $request->input('condicion') === 'credito' ? $request->fecha_vencimiento : null,
                'orden_compra_id'   => $orden?->id,
                'usuario_id'   => $request->user()->id,
                'total'        => 0,
            ]);

            $referencia = Str::limit("Factura {$factura->numero} · {$proveedor->nombre}", 120, '');
            $total = 0;

            foreach ($lineas as $linea) {
                $producto = $productos[$linea['producto_id']];
                $subtotal = round($linea['cantidad'] * $linea['precio_unitario'], 2);
                $total += $subtotal;

                FacturaDetalle::create([
                    'factura_id'      => $factura->id,
                    'producto_id'     => $producto->id,
                    'cantidad'        => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal'        => $subtotal,
                ]);

                // El stock lo sube el trigger al insertar la entrada (sección 4.2)
                MovimientoInventario::create([
                    'producto_id' => $producto->id,
                    'usuario_id'  => $request->user()->id,
                    'tipo'        => 'entrada',
                    'cantidad'    => $linea['cantidad'],
                    'referencia'  => $referencia,
                    'factura_id'  => $factura->id,
                ]);

                // Costo del producto = último precio de compra (el promedio queda en la ficha del proveedor)
                $producto->update(['costo' => $linea['precio_unitario']]);
            }

            $factura->update(['total' => round($total, 2)]);

            // Lo que realmente llegó contra lo pedido (lo que no vino queda en 0 = faltante)
            if ($orden) {
                $recibido = $lineas->keyBy('producto_id');
                foreach ($orden->detalles as $d) {
                    $d->update(['cantidad_recibida' => (float) ($recibido[$d->producto_id]['cantidad'] ?? 0)]);
                }
                $orden->update(['estado' => 'recibida']);
            }

            return $factura;
        });

        $factura->load(['proveedor', 'usuario', 'detalles.producto']);
        $n = $factura->detalles->count();

        return response()->json([
            'message' => "Factura {$factura->numero} registrada: {$n} producto(s) agregados al inventario.",
            'data'    => Snapshot::factura($factura, $this->verPrecios($request), true),
        ], 201);
    }

    /**
     * Anular (solo admin): la factura no se borra; se registran SALIDAS que revierten
     * sus entradas, así el kardex conserva todo el historial. Si parte de lo comprado
     * ya se usó, no se puede anular (el stock quedaría negativo).
     */
    public function anular(Request $request, Factura $factura): JsonResponse
    {
        $request->validate(
            ['motivo' => ['required', 'string', 'max:150']],
            ['motivo.required' => 'Escribe el motivo de la anulación.', 'motivo.max' => 'El motivo admite como máximo 150 caracteres.'],
        );

        if ($factura->estado === 'anulada') {
            return response()->json(['message' => 'Esta factura ya estaba anulada.'], 409);
        }

        if ($factura->pagos()->exists()) {
            return response()->json([
                'message' => 'No se puede anular: la factura ya tiene pagos registrados en Cuentas por pagar.',
            ], 409);
        }

        DB::transaction(function () use ($request, $factura) {
            $factura->load('detalles');
            $productos = Producto::whereIn('id', $factura->detalles->pluck('producto_id'))->lockForUpdate()->get()->keyBy('id');

            $faltantes = $factura->detalles
                ->filter(fn ($d) => (float) $productos[$d->producto_id]->stock < $d->cantidad)
                ->map(fn ($d) => "{$productos[$d->producto_id]->nombre} (hay {$productos[$d->producto_id]->stock}, la factura ingresó {$d->cantidad})");

            if ($faltantes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'factura' => 'No se puede anular: ya se usó parte de lo comprado. '.$faltantes->implode('; ').'.',
                ]);
            }

            foreach ($factura->detalles as $d) {
                MovimientoInventario::create([
                    'producto_id' => $d->producto_id,
                    'usuario_id'  => $request->user()->id,
                    'tipo'        => 'salida',
                    'cantidad'    => $d->cantidad,
                    'referencia'  => Str::limit("Anulación factura {$factura->numero}", 120, ''),
                    'factura_id'  => $factura->id,
                ]);
            }

            // Si recibía una orden de compra, la orden vuelve a quedar pendiente
            if ($factura->orden_compra_id) {
                OrdenCompra::where('id', $factura->orden_compra_id)->update(['estado' => 'pendiente']);
                OrdenCompraDetalle::where('orden_compra_id', $factura->orden_compra_id)->update(['cantidad_recibida' => null]);
            }

            $factura->update([
                'estado'           => 'anulada',
                'motivo_anulacion' => $request->motivo,
                'anulada_por'      => $request->user()->id,
                'anulada_at'       => now(),
            ]);
        });

        return response()->json(['message' => "Factura {$factura->numero} anulada: el stock se revirtió."]);
    }
}
