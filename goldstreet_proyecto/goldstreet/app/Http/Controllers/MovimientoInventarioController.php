<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMovimientoRequest;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Support\Snapshot;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovimientoInventarioController extends Controller
{
    /**
     * Matriz de permisos rol + clase de movimiento (sección 4.4 del documento):
     *   entradas → admin, bodega · salidas → admin, bodega, cocina (sección 2: bodega
     *   registra entradas/salidas, ej. despacho del almacén a cocina)
     *   mermas (salida con causa) → cualquier rol: la registra quien detecta la pérdida
     */
    private const PERMISOS = [
        'entrada' => ['admin', 'bodega'],
        'salida'  => ['admin', 'bodega', 'cocina'],
        'merma'   => ['admin', 'bodega', 'cocina'],
    ];

    private const MENSAJES = [
        'entrada' => 'Entrada registrada correctamente.',
        'salida'  => 'Salida registrada correctamente.',
        'merma'   => 'Merma registrada correctamente.',
    ];

    /**
     * Kardex con filtros (filtrarMovimientos, sección 4.3):
     *   ?tipo=entrada|salida|merma &desde=YYYY-MM-DD &hasta=YYYY-MM-DD &producto_id= &limite=
     *   (tipo=salida devuelve solo consumo; tipo=merma solo pérdidas)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'tipo'        => ['nullable', 'in:entrada,salida,merma,compra,produccion'],
            'desde'       => ['nullable', 'date'],
            'hasta'       => ['nullable', 'date', 'after_or_equal:desde'],
            'producto_id' => ['nullable', 'integer'],
        ], [
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        $movimientos = MovimientoInventario::with(['producto', 'usuario'])
            ->clase($request->tipo)
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->hasta))
            ->when($request->filled('producto_id'), fn ($q) => $q->where('producto_id', $request->integer('producto_id')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(min(500, max(1, (int) $request->query('limite', 100))))
            ->get();

        return response()->json([
            'data'  => Snapshot::movimientos($movimientos),
            'total' => $movimientos->count(),
        ]);
    }

    public function store(StoreMovimientoRequest $request): JsonResponse
    {
        $rol   = $request->user()->rol;
        $tipo  = $request->tipo;
        $clase = $request->filled('causa') ? 'merma' : $tipo;

        if (! in_array($rol, self::PERMISOS[$clase], true)) {
            return response()->json([
                'message' => "Acceso denegado: el rol '{$rol}' no puede registrar {$clase}s.",
            ], 403);
        }

        $movimiento = DB::transaction(function () use ($request) {
            // Bloqueo pesimista (sección 4.3): evita doble descuento si dos
            // usuarios registran movimientos del mismo producto a la vez.
            $producto = Producto::where('id', $request->producto_id)->lockForUpdate()->firstOrFail();

            if ($producto->estado !== 'activo') {
                throw new HttpResponseException(
                    response()->json(['message' => 'El producto está inactivo.'], 409)
                );
            }

            if ($request->tipo === 'salida' && (float) $producto->stock < (float) $request->cantidad) {
                throw ValidationException::withMessages([
                    'cantidad' => "Stock insuficiente: disponible {$producto->stock} {$producto->unidad}, solicitaste {$request->cantidad} {$producto->unidad}.",
                ]);
            }

            $mov = MovimientoInventario::create([
                'producto_id' => $producto->id,
                'usuario_id'  => $request->user()->id,
                'tipo'        => $request->tipo,
                'cantidad'    => $request->cantidad,
                'referencia'  => $request->referencia,
                'causa'       => $request->tipo === 'salida' ? $request->causa : null,
            ]);

            // El stock NO se toca en código: lo actualiza el trigger
            // trg_mov_stock (la BD es la única fuente de verdad, sección 4.2).

            return $mov;
        });

        $producto = Producto::with(['categoria', 'proveedor'])->findOrFail($request->producto_id);

        return response()->json([
            'message'  => self::MENSAJES[$clase],
            'data'     => ['id' => $movimiento->id, 'tipo' => $tipo, 'clase' => $clase],
            'producto' => Snapshot::producto($producto),
            'alerta'   => $producto->estado_stock !== 'optimo'
                ? "Atención: {$producto->nombre} quedó en estado '{$producto->estado_stock}' "
                  ."(stock: {$producto->stock} {$producto->unidad}, mínimo: {$producto->stock_minimo} {$producto->unidad})."
                : null,
        ], 201);
    }
}
