<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Support\Snapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Producto::with(['categoria', 'proveedor']);

        // Por defecto solo activos; ?todos=1 los incluye (útil en Postman)
        if (! $request->boolean('todos')) {
            $query->where('estado', 'activo');
        }
        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->integer('categoria_id'));
        }
        if ($request->filled('buscar')) {
            // Escapa los comodines de LIKE para que '%' y '_' se busquen literalmente
            $q = addcslashes(trim((string) $request->query('buscar')), '\\%_');
            $query->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        $productos = $query->orderBy('nombre')->get();

        return response()->json([
            'data'  => Snapshot::productos($productos),
            'total' => $productos->count(),
        ]);
    }

    public function show(Producto $producto): JsonResponse
    {
        return response()->json([
            'data' => Snapshot::producto($producto->load(['categoria', 'proveedor'])),
        ]);
    }

    public function store(StoreProductoRequest $request): JsonResponse
    {
        // El producto nace con stock 0 y el stock inicial se registra como
        // ENTRADA en el kardex: el trigger trg_mov_stock lo suma (sección 4.2).
        $producto = DB::transaction(function () use ($request) {
            $producto = Producto::create($request->safe()->except('stock'));

            if ((float) $request->stock > 0) {
                MovimientoInventario::create([
                    'producto_id' => $producto->id,
                    'usuario_id'  => $request->user()->id,
                    'tipo'        => 'entrada',
                    'cantidad'    => $request->stock,
                    'referencia'  => 'Stock inicial (alta de producto)',
                ]);
            }

            return $producto->fresh();
        });

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data'    => Snapshot::producto($producto->load(['categoria', 'proveedor'])),
        ], 201);
    }

    public function update(UpdateProductoRequest $request, Producto $producto): JsonResponse
    {
        $producto->update($request->validated());

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data'    => Snapshot::producto($producto->load(['categoria', 'proveedor'])),
        ]);
    }

    /**
     * Baja LÓGICA (no DELETE físico): el registro se conserva porque los
     * movimientos históricos lo referencian (integridad del kardex).
     */
    public function destroy(Producto $producto): JsonResponse
    {
        if ($producto->estado === 'inactivo') {
            return response()->json(['message' => 'El producto ya estaba dado de baja.'], 409);
        }

        $producto->update(['estado' => 'inactivo']);

        return response()->json([
            'message' => 'Producto dado de baja (baja lógica). El historial de movimientos se conserva.',
        ]);
    }
}
