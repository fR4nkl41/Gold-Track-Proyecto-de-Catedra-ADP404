{{-- <x-sidebar-link href="/inventario" icono="caja" :activo="true" roles="admin">Inventario</x-sidebar-link> --}}
@props(['href', 'icono' => null, 'activo' => false, 'roles' => null])

@php
  // Íconos de línea (24x24, trazo) para no depender de librerías externas
  $iconos = [
      'inicio'   => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
      'caja'     => 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8',
      'flechas'  => 'M7 4v16M7 20l-4-4M7 20l4-4M17 20V4M17 4l-4 4M17 4l4 4',
      'grafica'  => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
      'etiqueta' => 'M3 12V3h9l9 9-9 9-9-9zM7.5 7.5h.01',
      'factura'  => 'M7 3h10a1 1 0 011 1v17l-3-2-3 2-3-2-3 2V4a1 1 0 011-1zM9 8h6M9 12h6M9 16h3',
      'olla'     => 'M4 10h16v5a5 5 0 01-5 5H9a5 5 0 01-5-5v-5zM2 10h20M9 6.5c0-1.2 1-1.6 1-3M14 6.5c0-1.2 1-1.6 1-3',
      'libro'    => 'M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2V5zM4 19a2 2 0 012-2h13M8 7h7M8 11h5',
      'carrito'  => 'M3 4h2l2.4 11.2a2 2 0 002 1.6h7.7a2 2 0 002-1.5L21 8H6M9 21a1 1 0 100-2 1 1 0 000 2zM18 21a1 1 0 100-2 1 1 0 000 2z',
      'moneda'   => 'M12 3v18M16.5 7.5c0-1.7-2-3-4.5-3s-4.5 1.3-4.5 3 2 2.6 4.5 3 4.5 1.3 4.5 3-2 3-4.5 3-4.5-1.3-4.5-3',
      'usuarios' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
  ];
@endphp

<a href="{{ $href }}" @if($roles) data-roles="{{ $roles }}" @endif
   @if($activo) aria-current="page" @endif
   {{ $attributes->class([
       'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition',
       'bg-dorado/15 text-dorado font-semibold shadow-[inset_3px_0_0_#E3A21A]' => $activo,
       'text-crema/80 hover:bg-white/5 hover:text-dorado' => ! $activo,
   ]) }}>
  @if ($icono && isset($iconos[$icono]))
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="{{ $iconos[$icono] }}" />
    </svg>
  @endif
  <span>{{ $slot }}</span>
</a>
