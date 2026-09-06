<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProduccionRequest;
use App\Models\MovimientoInventario;
use App\Models\Produccion;
use App\Models\ProduccionResultado;
use App\Models\Producto;
use App\Support\Snapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Producción / porcionado (fase 2), como en el cuaderno de cocina:
 * "se partió 21 lb de puyazo, salieron 41 órdenes y 0.64 de desperdicio".
 * Genera tres tipos de movimiento, todos ligados a la producción:
 *   SALIDA de la materia prima aprovechada · MERMA (desperdicio) · ENTRADA de cada porción.
 */
class ProduccionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'desde'              => ['nullable', 'date'],
            'hasta'              => ['nullable', 'date', 'after_or_equal:desde'],
            'producto_origen_id' => ['nullable', 'integer'],
        ], [
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        $producciones = Produccion::with(['origen', 'usuario', 'resultados.producto'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->hasta))
            ->when($request->filled('producto_origen_id'), fn ($q) => $q->where('producto_origen_id', $request->integer('producto_origen_id')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $producciones->map(fn ($p) => Snapshot::produccion($p))->values(),
        ]);
    }

    public function store(StoreProduccionRequest $request): JsonResponse
    {
        $resultados = collect($request->resultados);
        $desperdicio = (float) ($request->desperdicio ?? 0);
        $usado = (float) $request->cantidad_origen;

        if ($resultados->pluck('producto_id')->contains((int) $request->producto_origen_id)) {
            throw ValidationException::withMessages([
                'resultados' => 'La materia prima no puede ser también un resultado de la producción.',
            ]);
        }

        // Rendimiento poco común (p. ej. 400 en vez de 40): pide confirmación antes de crear porciones
        $rinde = round($resultados->sum('cantidad') / $usado, 2);
        $limite = $this->rendimientoMaximo((int) $request->producto_origen_id);
        if ($rinde > $limite['maximo'] && ! $request->boolean('confirmar_rendimiento')) {
            $mensaje = "Rinde {$rinde} por unidad de materia prima; {$limite['referencia']}. Revisa las cantidades o confirma que es correcto.";

            return response()->json([
                'message'               => $mensaje,
                'requiere_confirmacion' => true,
                'errors'                => ['confirmar_rendimiento' => [$mensaje]],
            ], 422);
        }

        $produccion = DB::transaction(function () use ($request, $resultados, $desperdicio, $usado) {
            $ids = $resultados->pluck('producto_id')->push($request->producto_origen_id);
            $productos = Producto::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            $origen = $productos[$request->producto_origen_id];

            $inactivos = $productos->where('estado', '!=', 'activo')->pluck('nombre');
            if ($inactivos->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'producto_origen_id' => 'Estos productos están dados de baja: '.$inactivos->implode(', ').'.',
                ]);
            }

            if ((float) $origen->stock < $usado) {
                throw ValidationException::withMessages([
                    'cantidad_origen' => "Stock insuficiente de {$origen->nombre}: hay {$origen->stock} {$origen->unidad} y la producción usa {$usado} {$origen->unidad}.",
                ]);
            }

            $produccion = Produccion::create([
                'usuario_id'         => $request->user()->id,
                'producto_origen_id' => $origen->id,
                'cantidad_origen'    => $usado,
                'desperdicio'        => $desperdicio,
                'notas'              => $request->notas,
            ]);

            $resumen = $resultados->map(fn ($r) => "{$r['cantidad']} {$productos[$r['producto_id']]->nombre}")->implode(', ');
            $referencia = Str::limit("Producción #{$produccion->id}: {$origen->nombre} → {$resumen}", 120, '…');
            $base = ['usuario_id' => $request->user()->id, 'produccion_id' => $produccion->id, 'referencia' => $referencia];

            // 1) Materia prima aprovechada (no es consumo ni pérdida: se transformó)
            MovimientoInventario::create($base + [
                'producto_id' => $origen->id, 'tipo' => 'salida', 'cantidad' => round($usado - $desperdicio, 2),
            ]);

            // 2) Desperdicio = merma con causa "desperdicio" (aparece en el reporte de mermas)
            if ($desperdicio > 0) {
                MovimientoInventario::create($base + [
                    'producto_id' => $origen->id, 'tipo' => 'salida', 'cantidad' => $desperdicio, 'causa' => 'desperdicio',
                ]);
            }

            // 3) Porciones u órdenes que salieron
            foreach ($resultados as $r) {
                ProduccionResultado::create([
                    'produccion_id' => $produccion->id,
                    'producto_id'   => $r['producto_id'],
                    'cantidad'      => $r['cantidad'],
                ]);
                MovimientoInventario::create($base + [
                    'producto_id' => $r['producto_id'], 'tipo' => 'entrada', 'cantidad' => $r['cantidad'],
                ]);
            }

            // 4) Costo de cada porción = lo que costó la materia prima usada (desperdicio incluido) ÷ porciones obtenidas.
            //    Así el costo del plato y su food cost siguen al precio real de la carne.
            $costoPorcion = round($usado * (float) $origen->costo / $resultados->sum('cantidad'), 2);
            Producto::whereIn('id', $resultados->pluck('producto_id'))->update(['costo' => $costoPorcion]);

            return $produccion;
        });

        $produccion->load(['origen', 'usuario', 'resultados.producto']);
        $origen = $produccion->origen;

        return response()->json([
            'message' => "Producción registrada: aprovechamiento de {$produccion->aprovechamiento}%. Costo por porción: \${$produccion->resultados->first()->producto->costo}.",
            'data'    => Snapshot::produccion($produccion),
            'alerta'  => $origen->estado_stock !== 'optimo'
                ? "Atención: {$origen->nombre} quedó en estado '{$origen->estado_stock}' (stock: {$origen->stock} {$origen->unidad})."
                : null,
        ], 201);
    }

    /**
     * Hasta cuánto puede rendir una unidad de materia prima sin pedir confirmación:
     * el doble del promedio de sus últimas 20 producciones, o 10 por unidad si aún no tiene historial.
     */
    private function rendimientoMaximo(int $origenId): array
    {
        $promedio = Produccion::with('resultados')
            ->where('producto_origen_id', $origenId)
            ->latest('id')
            ->limit(20)
            ->get()
            ->avg(fn (Produccion $p) => $p->resultados->sum('cantidad') / (float) $p->cantidad_origen);

        return $promedio
            ? ['maximo' => round($promedio * 2, 2), 'referencia' => 'lo normal es '.round($promedio, 2)]
            : ['maximo' => 10.0, 'referencia' => 'sin historial, más de 10 por unidad es poco común'];
    }
}
