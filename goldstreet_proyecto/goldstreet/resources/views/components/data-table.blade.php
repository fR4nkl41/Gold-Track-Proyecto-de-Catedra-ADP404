{{--
  <x-data-table tbody="gs-tabla-productos" :columnas="['Código', 'Producto', ['Acciones', 'text-right']]" />
  Las filas las pinta el JS de cada página dentro del <tbody id="...">; mientras tanto
  se ven filas grises de carga (las mismas que App.filasCargando() en core.js).
  variante: "oscura" (encabezado negro) | "clara" (encabezado crema, para tarjetas)
--}}
@props(['tbody', 'columnas' => [], 'variante' => 'oscura', 'minWidth' => null])

<div class="overflow-x-auto">
  <table class="w-full text-sm tabular-nums" @if($minWidth) style="min-width: {{ $minWidth }}" @endif>
    <thead @class([
        'text-left uppercase tracking-wider',
        'bg-negro text-[11px] text-crema/80' => $variante === 'oscura',
        'bg-plato/60 text-[11px] text-stone-500' => $variante === 'clara',
    ])>
      <tr>
        @foreach ($columnas as $col)
          @php [$texto, $clase] = is_array($col) ? $col : [$col, '']; @endphp
          <th class="px-4 font-semibold {{ $variante === 'oscura' ? 'py-3' : 'py-2.5' }} {{ $clase }}">{{ $texto }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody id="{{ $tbody }}">
      @for ($i = 0; $i < 3; $i++)
        <tr class="border-t border-stone-100">
          @foreach ($columnas as $col)
            <td class="px-4 py-3"><span class="gs-skeleton" style="width: {{ [70, 50, 85][$i] }}%"></span></td>
          @endforeach
        </tr>
      @endfor
    </tbody>
  </table>
</div>
