<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Support\Snapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $productos = Producto::with(['categoria', 'proveedor'])
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        // obtenerProductosEnAlerta() (4.3): stock <= mínimo
        $criticos = $productos
            ->filter(fn ($p) => $p->estado_stock !== 'optimo')
            ->values();

        $ultimos = MovimientoInventario::with(['producto', 'usuario'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $hoy = MovimientoInventario::whereDate('created_at', today())->get(['tipo', 'causa', 'factura_id', 'produccion_id']);

        $mermasMes = MovimientoInventario::with('producto:id,costo')
            ->clase('merma')
            ->whereDate('created_at', '>=', today()->startOfMonth())
            ->get(['producto_id', 'cantidad']);

        return response()->json([
            'resumen' => [
                'totalProductos'  => $productos->count(),
                'alertasBajo'     => $productos->where('estado_stock', 'bajo')->count(),
                'agotados'        => $productos->where('estado_stock', 'agotado')->count(),
                'valorInventario' => round((float) $productos->sum(fn ($p) => $p->stock * $p->costo), 2),
                'entradasHoy'     => $hoy->where('tipo', 'entrada')->count(),
                'salidasHoy'      => $hoy->filter(fn ($m) => $m->es_consumo)->count(),
                'mermasHoy'       => $hoy->where('tipo', 'salida')->whereNotNull('causa')->count(),
                'movimientosHoy'  => $hoy->count(),
                'valorMermasMes'  => round((float) $mermasMes->sum(fn ($m) => $m->cantidad * ($m->producto?->costo ?? 0)), 2),
            ],
            'criticos'           => Snapshot::productos($criticos),
            'inventarioReciente' => $this->inventarioReciente(),
            'ultimosMovimientos' => Snapshot::movimientos($ultimos),
            'consumoSemanal'     => $this->consumoSemanal(),
        ]);
    }

    /** "Tabla de inventario reciente" (5.1): productos con movimiento más reciente. */
    private function inventarioReciente(): array
    {
        return Producto::with('categoria')
            ->where('estado', 'activo')
            ->withMax('movimientos', 'created_at')
            ->orderByDesc('movimientos_max_created_at')
            ->orderBy('nombre')
            ->limit(6)
            ->get()
            ->map(fn ($p) => Snapshot::producto($p) + [
                'ultimo_movimiento' => $p->movimientos_max_created_at
                    ? Carbon::parse($p->movimientos_max_created_at)->format('d/m/Y H:i')
                    : null,
            ])
            ->all();
    }

    /**
     * Valor en $ de entradas, salidas (consumo) y mermas de los últimos 7 días.
     * Se usa el valor (cantidad × costo) porque las unidades (kg, L...) no se pueden sumar entre sí.
     */
    private function consumoSemanal(): array
    {
        $inicio = today()->subDays(6);

        $movs = MovimientoInventario::with('producto:id,costo')
            ->whereDate('created_at', '>=', $inicio)
            ->get(['producto_id', 'tipo', 'causa', 'factura_id', 'produccion_id', 'cantidad', 'created_at']);

        $valor = fn ($coleccion) => round((float) $coleccion
            ->sum(fn ($m) => $m->cantidad * ($m->producto?->costo ?? 0)), 2);

        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $dia = $inicio->copy()->addDays($i);
            $delDia = $movs->filter(fn ($m) => $m->created_at?->isSameDay($dia));

            $dias[] = [
                'fecha'    => $dia->toDateString(),
                'etiqueta' => ucfirst($dia->locale('es')->isoFormat('ddd D')),
                'entradas' => $valor($delDia->where('tipo', 'entrada')),
                'salidas'  => $valor($delDia->filter(fn ($m) => $m->es_consumo)),
                'mermas'   => $valor($delDia->where('tipo', 'salida')->whereNotNull('causa')),
            ];
        }

        return $dias;
    }
}
