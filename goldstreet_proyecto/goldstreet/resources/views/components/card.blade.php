{{--
  <x-card title="Productos críticos" subtitle="bajo mínimo o agotados">
    <x-slot:acciones><a href="...">Ver todos</a></x-slot:acciones>
    ...contenido...
  </x-card>
  Sin title no dibuja encabezado. :padding="false" para tablas pegadas al borde.
--}}
@props(['title' => null, 'subtitle' => null, 'padding' => true])

<section {{ $attributes->class(['bg-white rounded-xl border border-plato overflow-hidden']) }}>
  @if ($title)
    <header class="px-5 py-4 border-b border-plato flex items-center justify-between gap-3">
      <div>
        <h2 class="font-display text-lg font-bold text-negro">{{ $title }}</h2>
        @if ($subtitle)
          <p class="text-xs text-stone-500">{{ $subtitle }}</p>
        @endif
      </div>
      @isset($acciones)
        <div class="flex items-center gap-2 print:hidden">{{ $acciones }}</div>
      @endisset
    </header>
  @endif

  <div @class(['p-5' => $padding])>
    {{ $slot }}
  </div>
</section>
