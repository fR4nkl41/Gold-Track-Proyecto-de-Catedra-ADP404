<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Models\PagoFactura;
use App\Support\Snapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cuentas por pagar (fase 3, solo admin): facturas al crédito con saldo, abonos,
 * vencidas y por vencer. Las de contado se consideran pagadas al recibirlas.
 */
class CuentaPagarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'proveedor_id' => ['nullable', 'integer'],
            'estado'       => ['nullable', 'in:pendiente,vencida,pagada,todas'],
        ]);

        $facturas = Factura::with(['proveedor', 'usuario', 'pagos'])
            ->withCount('detalles')
            ->where('condicion', 'credito')
            ->where('estado', 'registrada')
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get();

        $conSaldo = $facturas->filter(fn ($f) => $f->saldo > 0);
        $estado = $request->input('estado', 'pendiente');
        $lista = match ($estado) {
            'vencida' => $conSaldo->where('estado_pago', 'vencida'),
            'pagada'  => $facturas->where('estado_pago', 'pagada'),
            'todas'   => $facturas,
            default   => $conSaldo,                       // pendiente = todo lo que se debe (vencido o no)
        };

        $enSemana = today()->addDays(7);

        return response()->json([
            'data'    => $lista->map(fn ($f) => Snapshot::factura($f, true) + [
                'dias' => $f->fecha_vencimiento ? (int) today()->diffInDays($f->fecha_vencimiento, false) : null,
            ])->values(),
            'porProveedor' => $conSaldo->groupBy('proveedor_id')->map(fn ($g) => [
                'proveedor_id' => $g->first()->proveedor_id,
                'proveedor'    => $g->first()->proveedor?->nombre,
                'facturas'     => $g->count(),
                'saldo'        => round((float) $g->sum('saldo'), 2),
                'vencido'      => round((float) $g->where('estado_pago', 'vencida')->sum('saldo'), 2),
            ])->sortByDesc('saldo')->values(),
            'totales' => [
                'pendiente'   => round((float) $conSaldo->sum('saldo'), 2),
                'vencido'     => round((float) $conSaldo->where('estado_pago', 'vencida')->sum('saldo'), 2),
                'porVencer7'  => round((float) $conSaldo->where('estado_pago', 'pendiente')
                    ->filter(fn ($f) => $f->fecha_vencimiento && $f->fecha_vencimiento->lte($enSemana))->sum('saldo'), 2),
                'facturas'    => $conSaldo->count(),
                'pagadoMes'   => round((float) PagoFactura::whereDate('fecha', '>=', today()->startOfMonth())->sum('monto'), 2),
            ],
        ]);
    }

    /** Registrar un abono (total o parcial) a una factura al crédito. */
    public function pagar(Request $request, Factura $factura): JsonResponse
    {
        $request->validate([
            'monto'      => ['required', 'numeric', 'min:0.01'],
            'fecha'      => ['required', 'date', 'before_or_equal:today'],
            'metodo'     => ['required', Rule::in(array_keys(PagoFactura::METODOS))],
            'referencia' => ['nullable', 'string', 'max:60'],
        ], [
            'monto.required'        => 'Escribe el monto del pago.',
            'monto.min'             => 'El monto debe ser mayor que cero.',
            'fecha.before_or_equal' => 'La fecha del pago no puede ser futura.',
            'metodo.in'             => 'El método de pago no es válido.',
            'referencia.max'        => 'La referencia admite como máximo 60 caracteres.',
        ]);

        if ($factura->condicion !== 'credito' || $factura->estado !== 'registrada') {
            return response()->json(['message' => 'Solo se registran pagos a facturas al crédito que no estén anuladas.'], 409);
        }

        DB::transaction(function () use ($request, $factura) {
            $factura = Factura::with('pagos')->lockForUpdate()->findOrFail($factura->id);

            if ($request->monto > $factura->saldo + 0.001) {
                throw ValidationException::withMessages([
                    'monto' => 'El pago ($'.number_format($request->monto, 2).') es mayor que el saldo ($'.number_format($factura->saldo, 2).').',
                ]);
            }

            PagoFactura::create([
                'factura_id' => $factura->id,
                'monto'      => $request->monto,
                'fecha'      => $request->fecha,
                'metodo'     => $request->metodo,
                'referencia' => $request->referencia,
                'usuario_id' => $request->user()->id,
            ]);
        });

        $factura->load(['proveedor', 'usuario', 'pagos.usuario', 'detalles.producto']);
        $msg = $factura->saldo > 0
            ? 'Pago registrado. Saldo pendiente: $'.number_format($factura->saldo, 2).'.'
            : "Pago registrado. La factura {$factura->numero} quedó pagada.";

        return response()->json(['message' => $msg, 'data' => Snapshot::factura($factura, true, true)], 201);
    }
}
