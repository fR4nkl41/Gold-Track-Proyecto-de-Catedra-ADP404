{{-- <x-footer /> o <x-footer :oscuro="true" /> : pie con la versión del sistema (sección 6) --}}
@props(['oscuro' => false])

<footer {{ $attributes->class([
    'px-4 py-5 text-center text-xs',
    'text-crema/50' => $oscuro,
    'text-stone-400 border-t border-plato print:hidden' => ! $oscuro,
]) }}>
  <span class="font-semibold">GoldTrack v{{ config('app.version') }}</span>
  · Restaurante Gold Street · Administración de Proyectos — UDB · {{ date('Y') }}
</footer>
