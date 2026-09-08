<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaRequest;
use App\Http\Requests\ProveedorRequest;
use App\Models\Categoria;
use App\Models\FacturaDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Snapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/** Catálogos: lectura para cualquier rol · alta/edición/eliminación solo admin. */
class CatalogoController extends Controller
{
    // ---- Categorías ---------------------------------------------------------

    public function categorias(): JsonResponse
    {
        return response()->json(['data' => Categoria::withCount('productos')->orderBy('nombre')->get()]);
    }

    public function storeCategoria(CategoriaRequest $request): JsonResponse
    {
        $categoria = Categoria::create($request->validated());

        return response()->json(['message' => 'Categoría creada correctamente.', 'data' => $categoria], 201);
    }

    public function updateCategoria(CategoriaRequest $request, Categoria $categoria): JsonResponse
    {
        $categoria->update($request->validated());

        return response()->json(['message' => 'Categoría actualizada correctamente.', 'data' => $categoria]);
    }

    public function destroyCategoria(Categoria $categoria): JsonResponse
    {
        if (Producto::where('categoria_id', $categoria->id)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay productos asignados a esta categoría.',
            ], 409);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }

    // ---- Proveedores --------------------------------------------------------

    public function proveedores(): JsonResponse
    {
        return response()->json(['data' => Proveedor::withCount('productos')->orderBy('nombre')->get()]);
    }

    /**
     * Ficha del proveedor (solo admin): contacto, qué productos se le compran y a qué precio.
     * Los precios salen de las facturas registradas (no anuladas), no se escriben a mano.
     */
    public function fichaProveedor(Proveedor $proveedor): JsonResponse
    {
        $detalles = FacturaDetalle::with(['producto', 'factura'])
            ->whereHas('factura', fn ($q) => $q->where('proveedor_id', $proveedor->id)->where('estado', 'registrada'))
            ->get();

        $comprados = $detalles->groupBy('producto_id')->map(function ($grupo) {
            // Orden cronológico por fecha de factura (y orden de registro) para saber el último precio
            $ordenado = $grupo->sortBy(fn ($d) => $d->factura->fecha->format('Ymd').str_pad((string) $d->id, 10, '0', STR_PAD_LEFT));
            $ultimo = $ordenado->last();
            $anterior = $ordenado->count() > 1 ? $ordenado->slice(-2, 1)->first() : null;
            $cantidad = (float) $grupo->sum('cantidad');
            $monto = (float) $grupo->sum('subtotal');

            return [
                'producto_id'     => $ultimo->producto_id,
                'codigo'          => $ultimo->producto?->codigo,
                'producto'        => $ultimo->producto?->nombre,
                'unidad'          => $ultimo->producto?->unidad,
                'compras'         => $grupo->count(),
                'cantidad'        => round($cantidad, 2),
                'monto'           => round($monto, 2),
                'ultimo_precio'   => $ultimo->precio_unitario,
                'precio_anterior' => $anterior?->precio_unitario,
                'ultima_compra'   => $ultimo->factura->fecha->format('d/m/Y'),
                'precio_promedio' => $cantidad > 0 ? round($monto / $cantidad, 2) : 0,
                'precio_min'      => (float) $grupo->min('precio_unitario'),
                'precio_max'      => (float) $grupo->max('precio_unitario'),
            ];
        })->sortByDesc('monto')->values();

        $facturas = $proveedor->facturas()->with(['usuario', 'anuladaPor', 'pagos'])->withCount('detalles')
            ->orderByDesc('fecha')->orderByDesc('id')->limit(30)->get();

        $registradas = $proveedor->facturas()->where('estado', 'registrada');

        return response()->json([
            'proveedor'  => $proveedor->only(['id', 'nombre', 'contacto', 'telefono', 'correo', 'direccion']),
            'asignados'  => $proveedor->productos()->where('estado', 'activo')->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'unidad']),
            'productos'  => $comprados,
            'facturas'   => $facturas->map(fn ($f) => Snapshot::factura($f, true))->values(),
            'totales'    => [
                'facturas'      => (clone $registradas)->count(),
                'total'         => round((float) (clone $registradas)->sum('total'), 2),
                'mes'           => round((float) (clone $registradas)->whereDate('fecha', '>=', today()->startOfMonth())->sum('total'), 2),
                'productos'     => $comprados->count(),
                'ultima_compra' => ($ultima = (clone $registradas)->max('fecha')) ? Carbon::parse($ultima)->format('d/m/Y') : null,
            ],
        ]);
    }

    public function storeProveedor(ProveedorRequest $request): JsonResponse
    {
        $proveedor = Proveedor::create($request->validated());

        return response()->json(['message' => 'Proveedor creado correctamente.', 'data' => $proveedor], 201);
    }

    public function updateProveedor(ProveedorRequest $request, Proveedor $proveedor): JsonResponse
    {
        $proveedor->update($request->validated());

        return response()->json(['message' => 'Proveedor actualizado correctamente.', 'data' => $proveedor]);
    }

    public function destroyProveedor(Proveedor $proveedor): JsonResponse
    {
        if ($proveedor->facturas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el proveedor tiene facturas registradas (son parte del historial).',
            ], 409);
        }

        if (Producto::where('proveedor_id', $proveedor->id)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay productos que usan este proveedor.',
            ], 409);
        }

        $proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado.']);
    }
}
