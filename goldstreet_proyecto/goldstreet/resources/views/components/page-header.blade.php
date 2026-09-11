{{-- <x-page-header title="Inventario" subtitle="...">  [botones de acción en el slot]  </x-page-header> --}}
@props(['title', 'subtitle' => null])

<div class="flex items-end justify-between flex-wrap gap-3 mb-6">
  <div>
    <h1 class="font-display text-3xl font-bold text-negro">{{ $title }}</h1>
    @if ($subtitle)
      <p class="text-stone-500 text-sm">{{ $subtitle }}</p>
    @endif
  </div>
  @if ($slot->isNotEmpty())
    <div class="flex flex-wrap gap-2 print:hidden">{{ $slot }}</div>
  @endif
</div>
