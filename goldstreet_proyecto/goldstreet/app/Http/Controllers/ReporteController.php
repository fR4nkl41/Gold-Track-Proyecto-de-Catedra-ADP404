<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Produccion;
use App\Models\Producto;
use App\Support\Snapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 2 · Reportes (solo admin): existencias, consumo por período, mermas y kardex.
 * La exportación a CSV / impresión se hace en el frontend con estos mismos datos.
 */
class ReporteController extends Controller
{
    /** Reporte de existencias: stock y valor actual, con totales por categoría. */
    public function existencias(): JsonResponse
    {
        $productos = Producto::with(['categoria', 'proveedor'])
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        $porCategoria = $productos
            ->groupBy(fn ($p) => $p->categoria?->nombre ?? 'Sin categoría')
            ->map(fn ($grupo, $nombre) => [
                'categoria' => $nombre,
                'productos' => $grupo->count(),
                'valor'     => round((float) $grupo->sum(fn ($p) => $p->stock * $p->costo), 2),
            ])
            ->sortByDesc('valor')
            ->values();

        return response()->json([
            'data'         => Snapshot::productos($productos),
            'porCategoria' => $porCategoria,
            'totales'      => [
                'productos' => $productos->count(),
                'valor'     => round((float) $productos->sum(fn ($p) => $p->stock * $p->costo), 2),
                'alertas'   => $productos->filter(fn ($p) => $p->estado_stock !== 'optimo')->count(),
            ],
        ]);
    }

    /**
     * Reporte de consumo: entradas, salidas (consumo) y mermas por producto en un rango de fechas.
     * Las mermas se separan del consumo para no inflar lo que realmente usó la cocina.
     */
    public function consumo(Request $request): JsonResponse
    {
        $rango = $this->validarRango($request);

        $movs = MovimientoInventario::with('producto.categoria')
            ->whereDate('created_at', '>=', $rango['desde'])
            ->whereDate('created_at', '<=', $rango['hasta'])
            ->get();

        $filas = $movs->groupBy('producto_id')->map(function ($grupo) {
            $p = $grupo->first()->producto;
            $costo = $p?->costo ?? 0;
            $entradas = (float) $grupo->where('tipo', 'entrada')->sum('cantidad');
            $salidas  = (float) $grupo->filter(fn ($m) => $m->es_consumo)->sum('cantidad');
            $mermas   = (float) $grupo->where('tipo', 'salida')->whereNotNull('causa')->sum('cantidad');
            // Materia prima transformada en porciones (Producción): no es consumo ni pérdida
            $producido = (float) $grupo->where('tipo', 'salida')->whereNull('causa')->whereNotNull('produccion_id')->sum('cantidad');

            return [
                'producto'      => $p?->nombre,
                'codigo'        => $p?->codigo,
                'categoria'     => $p?->categoria?->nombre,
                'unidad'        => $p?->unidad,
                'entradas'      => round($entradas, 2),
                'salidas'       => round($salidas, 2),
                'mermas'        => round($mermas, 2),
                'produccion'    => round($producido, 2),
                'costoSalidas'  => round($salidas * $costo, 2),
                'costoMermas'   => round($mermas * $costo, 2),
                'movimientos'   => $grupo->count(),
            ];
        })->sortByDesc('costoSalidas')->values();

        return response()->json([
            'desde'   => $rango['desde'],
            'hasta'   => $rango['hasta'],
            'data'    => $filas,
            'totales' => [
                'movimientos'  => $movs->count(),
                'costoSalidas' => round((float) $filas->sum('costoSalidas'), 2),
                'costoMermas'  => round((float) $filas->sum('costoMermas'), 2),
            ],
        ]);
    }

    /** Reporte de mermas: pérdidas por producto y causa, valorizadas al costo actual. */
    public function mermas(Request $request): JsonResponse
    {
        $rango = $this->validarRango($request);

        $movs = MovimientoInventario::with(['producto.categoria', 'usuario'])
            ->clase('merma')
            ->whereDate('created_at', '>=', $rango['desde'])
            ->whereDate('created_at', '<=', $rango['hasta'])
            ->orderByDesc('created_at')
            ->get();

        $valor = fn ($m) => $m->cantidad * ($m->producto?->costo ?? 0);

        $porCausa = collect(MovimientoInventario::CAUSAS)->map(fn ($etiqueta, $clave) => [
            'causa' => $etiqueta,
            'veces' => $movs->where('causa', $clave)->count(),
            'valor' => round((float) $movs->where('causa', $clave)->sum($valor), 2),
        ])->values();

        $porProducto = $movs->groupBy('producto_id')->map(function ($grupo) use ($valor) {
            $p = $grupo->first()->producto;

            return [
                'codigo'    => $p?->codigo,
                'producto'  => $p?->nombre,
                'categoria' => $p?->categoria?->nombre,
                'unidad'    => $p?->unidad,
                'cantidad'  => round((float) $grupo->sum('cantidad'), 2),
                'valor'     => round((float) $grupo->sum($valor), 2),
                'veces'     => $grupo->count(),
                'causas'    => $grupo->pluck('causa')->unique()
                    ->map(fn ($c) => MovimientoInventario::CAUSAS[$c])->implode(', '),
            ];
        })->sortByDesc('valor')->values();

        return response()->json([
            'desde'       => $rango['desde'],
            'hasta'       => $rango['hasta'],
            'data'        => $porProducto,
            'porCausa'    => $porCausa,
            'totales'     => [
                'registros' => $movs->count(),
                'valor'     => round((float) $movs->sum($valor), 2),
            ],
        ]);
    }

    /**
     * Kardex de un producto con saldo después de cada movimiento.
     * El saldo se reconstruye hacia atrás desde el stock actual (fuente de verdad),
     * así cuadra aunque el producto tuviera stock sembrado antes del primer movimiento.
     */
    public function kardex(Request $request, Producto $producto): JsonResponse
    {
        $rango = $this->validarRango($request);

        $movs = MovimientoInventario::with('usuario')
            ->where('producto_id', $producto->id)
            ->whereDate('created_at', '>=', $rango['desde'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $saldo = (float) $producto->stock;
        $filas = [];
        foreach ($movs as $m) {
            $efecto = $m->tipo === 'entrada' ? $m->cantidad : -$m->cantidad;
            if ($m->created_at->toDateString() <= $rango['hasta']) {
                $filas[] = [
                    'fecha'      => $m->created_at->format('d/m/Y H:i'),
                    'tipo'       => $m->tipo,
                    'es_merma'   => $m->es_merma,
                    'clase'      => $m->clase,
                    'causa'      => $m->causa ? MovimientoInventario::CAUSAS[$m->causa] : null,
                    'entrada'    => $m->tipo === 'entrada' ? (float) $m->cantidad : null,
                    'salida'     => $m->tipo === 'salida' ? (float) $m->cantidad : null,
                    'saldo'      => round($saldo, 2),
                    'usuario'    => $m->usuario?->nombre,
                    'referencia' => $m->referencia,
                ];
            }
            $saldo -= $efecto;
        }

        return response()->json([
            'producto'     => Snapshot::producto($producto->load(['categoria', 'proveedor'])),
            'desde'        => $rango['desde'],
            'hasta'        => $rango['hasta'],
            'saldoInicial' => round($saldo, 2),
            'data'         => array_reverse($filas),
        ]);
    }

    /**
     * Rendimiento de producción por materia prima: cuánto se usó, cuánto se desperdició,
     * % de aprovechamiento y cuántas porciones salen por cada unidad (ej. 1.95 órdenes por lb).
     */
    public function rendimiento(Request $request): JsonResponse
    {
        $rango = $this->validarRango($request);

        $producciones = Produccion::with(['origen.categoria', 'resultados.producto'])
            ->whereDate('created_at', '>=', $rango['desde'])
            ->whereDate('created_at', '<=', $rango['hasta'])
            ->get();

        $filas = $producciones->groupBy('producto_origen_id')->map(function ($grupo) {
            $origen = $grupo->first()->origen;
            $usado = (float) $grupo->sum('cantidad_origen');
            $desperdicio = (float) $grupo->sum('desperdicio');

            $resultados = $grupo->flatMap->resultados->groupBy('producto_id')->map(fn ($rs) => [
                'producto'   => $rs->first()->producto?->nombre,
                'unidad'     => $rs->first()->producto?->unidad,
                'cantidad'   => round((float) $rs->sum('cantidad'), 2),
                'por_unidad' => $usado > 0 ? round($rs->sum('cantidad') / $usado, 2) : 0,
            ])->values();

            return [
                'codigo'           => $origen?->codigo,
                'origen'           => $origen?->nombre,
                'unidad'           => $origen?->unidad,
                'veces'            => $grupo->count(),
                'usado'            => round($usado, 2),
                'desperdicio'      => round($desperdicio, 2),
                'aprovechamiento'  => $usado > 0 ? round(($usado - $desperdicio) / $usado * 100, 1) : 0,
                'costoUsado'       => round($usado * ($origen?->costo ?? 0), 2),
                'costoDesperdicio' => round($desperdicio * ($origen?->costo ?? 0), 2),
                'resultados'       => $resultados,
            ];
        })->sortByDesc('costoUsado')->values();

        $usado = (float) $producciones->sum('cantidad_origen');

        return response()->json([
            'desde'   => $rango['desde'],
            'hasta'   => $rango['hasta'],
            'data'    => $filas,
            'totales' => [
                'producciones'     => $producciones->count(),
                'costoUsado'       => round((float) $filas->sum('costoUsado'), 2),
                'costoDesperdicio' => round((float) $filas->sum('costoDesperdicio'), 2),
                'aprovechamiento'  => $usado > 0
                    ? round(($usado - (float) $producciones->sum('desperdicio')) / $usado * 100, 1) : 0,
            ],
        ]);
    }

    /** Rango por defecto: últimos 30 días. */
    private function validarRango(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ], [
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        return [
            'desde' => $request->input('desde', today()->subDays(29)->toDateString()),
            'hasta' => $request->input('hasta', today()->toDateString()),
        ];
    }
}
