{{-- <x-stat-card id="gs-stat-valor" label="Valor del inventario" tono="dorado" valor="$0.00" detalle="a costo" /> --}}
@props(['id', 'label', 'valor' => '0', 'tono' => 'neutro', 'detalle' => null])

@php
  // destacado = tarjeta negra con cifra dorada (la que más importa de la fila)
  $tonos = [
      'neutro'    => ['caja' => 'bg-white border-plato',  'cifra' => 'text-negro',       'texto' => 'text-stone-500'],
      'dorado'    => ['caja' => 'bg-white border-plato',  'cifra' => 'text-ocre',        'texto' => 'text-stone-500'],
      'verde'     => ['caja' => 'bg-white border-plato',  'cifra' => 'text-emerald-700', 'texto' => 'text-stone-500'],
      'aviso'     => ['caja' => 'bg-white border-plato',  'cifra' => 'text-amber-700',   'texto' => 'text-stone-500'],
      'rojo'      => ['caja' => 'bg-white border-plato',  'cifra' => 'text-red-700',     'texto' => 'text-stone-500'],
      'destacado' => ['caja' => 'bg-negro border-negro',  'cifra' => 'text-dorado',      'texto' => 'text-crema/60'],
  ];
  $t = $tonos[$tono] ?? $tonos['neutro'];
@endphp

<div {{ $attributes->class(['rounded-xl border p-4 sm:p-5', $t['caja']]) }}>
  <p class="text-[11px] font-semibold uppercase tracking-wider {{ $t['texto'] }}">{{ $label }}</p>
  <p id="{{ $id }}" class="font-display text-3xl font-bold mt-1 tabular-nums {{ $t['cifra'] }}">{{ $valor }}</p>
  @if ($detalle)
    <p @if($id) id="{{ $id }}-detalle" @endif class="text-xs mt-0.5 {{ $t['texto'] }}">{{ $detalle }}</p>
  @endif
</div>
