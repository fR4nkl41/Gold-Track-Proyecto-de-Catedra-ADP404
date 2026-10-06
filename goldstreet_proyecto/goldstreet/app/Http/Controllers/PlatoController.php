<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatoRequest;
use App\Models\Plato;
use App\Models\RecetaIngrediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recetas y costo por plato (fase 3). Cocina consulta las recetas (ficha técnica)
 * sin costos; el admin ve costo, margen y food cost, y administra el menú.
 */
class PlatoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conCostos = $request->user()->rol === 'admin';

        $platos = Plato::with('ingredientes.producto')
            ->when(! $request->boolean('todos'), fn ($q) => $q->where('activo', true))
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get();

        $datos = $platos->map(fn ($p) => $this->snapshot($p, $conCostos))->values();

        return response()->json([
            'data'    => $datos,
            'totales' => $conCostos ? [
                'platos'         => $platos->count(),
                'foodCostMedio'  => $platos->count() ? round($platos->avg('food_cost'), 1) : 0,
                'sobreLimite'    => $platos->filter(fn ($p) => $p->food_cost > Plato::FOOD_COST_LIMITE)->count(),
                'ideal'          => Plato::FOOD_COST_IDEAL,
                'limite'         => Plato::FOOD_COST_LIMITE,
            ] : null,
        ]);
    }

    public function store(PlatoRequest $request): JsonResponse
    {
        $plato = DB::transaction(function () use ($request) {
            $plato = Plato::create($request->safe()->except('ingredientes'));
            $this->guardarIngredientes($plato, $request->ingredientes);

            return $plato;
        });

        return response()->json([
            'message' => "Plato \"{$plato->nombre}\" creado.",
            'data'    => $this->snapshot($plato->load('ingredientes.producto'), true),
        ], 201);
    }

    public function update(PlatoRequest $request, Plato $plato): JsonResponse
    {
        DB::transaction(function () use ($request, $plato) {
            $plato->update($request->safe()->except('ingredientes'));
            $plato->ingredientes()->delete();
            $this->guardarIngredientes($plato, $request->ingredientes);
        });

        return response()->json([
            'message' => "Plato \"{$plato->nombre}\" actualizado.",
            'data'    => $this->snapshot($plato->load('ingredientes.producto'), true),
        ]);
    }

    public function destroy(Plato $plato): JsonResponse
    {
        $plato->delete();   // la receta se borra en cascada

        return response()->json(['message' => "Plato \"{$plato->nombre}\" eliminado del menú."]);
    }

    private function guardarIngredientes(Plato $plato, array $ingredientes): void
    {
        foreach ($ingredientes as $i) {
            RecetaIngrediente::create([
                'plato_id'    => $plato->id,
                'producto_id' => $i['producto_id'],
                'cantidad'    => $i['cantidad'],
            ]);
        }
    }

    private function snapshot(Plato $p, bool $conCostos): array
    {
        $datos = [
            'id'                 => $p->id,
            'nombre'             => $p->nombre,
            'categoria'          => $p->categoria,
            'descripcion'        => $p->descripcion,
            'activo'             => $p->activo,
            'porciones_posibles' => $p->porciones_posibles,
            'ingredientes'       => $p->ingredientes->map(fn ($i) => [
                'producto_id' => $i->producto_id,
                'producto'    => $i->producto?->nombre,
                'unidad'      => $i->producto?->unidad,
                'cantidad'    => $i->cantidad,
                'stock'       => (float) ($i->producto?->stock ?? 0),
                'costo'       => $conCostos ? round($i->cantidad * ($i->producto?->costo ?? 0), 2) : null,
            ])->values()->all(),
        ];

        if ($conCostos) {
            $datos += [
                'precio_venta' => $p->precio_venta,
                'costo'        => $p->costo,
                'margen'       => $p->margen,
                'food_cost'    => $p->food_cost,
            ];
        }

        return $datos;
    }
}
