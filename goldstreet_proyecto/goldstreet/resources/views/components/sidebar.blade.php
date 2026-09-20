{{--
  <x-sidebar active="dashboard"> : barra lateral con accesos según rol (sección 6).
  Para agregar una opción nueva basta con sumar un elemento a $secciones.
  'roles' => null significa visible para todos; 'admin' o 'admin,bodega' la restringe
  (aplica a la sección completa o a un enlace individual).
--}}
@props(['active' => ''])

@php
  $secciones = [
      ['titulo' => 'Operación', 'roles' => null, 'items' => [
          ['key' => 'dashboard',   'href' => '/dashboard',   'texto' => 'Dashboard',   'icono' => 'inicio'],
          ['key' => 'inventario',  'href' => '/inventario',  'texto' => 'Inventario',  'icono' => 'caja'],
          ['key' => 'movimientos', 'href' => '/movimientos', 'texto' => 'Movimientos', 'icono' => 'flechas'],
          ['key' => 'produccion',  'href' => '/produccion',  'texto' => 'Producción',  'icono' => 'olla'],
          ['key' => 'recetas',     'href' => '/recetas',     'texto' => 'Recetas',     'icono' => 'libro'],
      ]],
      ['titulo' => 'Compras', 'roles' => 'admin,bodega', 'items' => [
          ['key' => 'compras',  'href' => '/compras',           'texto' => 'Lista y órdenes',   'icono' => 'carrito'],
          ['key' => 'facturas', 'href' => '/facturas',          'texto' => 'Facturas',          'icono' => 'factura'],
          ['key' => 'cuentas',  'href' => '/cuentas-por-pagar', 'texto' => 'Cuentas por pagar', 'icono' => 'moneda', 'roles' => 'admin'],
      ]],
      ['titulo' => 'Administración', 'roles' => 'admin', 'items' => [
          ['key' => 'reportes',  'href' => '/reportes',  'texto' => 'Reportes',  'icono' => 'grafica'],
          ['key' => 'catalogos', 'href' => '/catalogos', 'texto' => 'Catálogos', 'icono' => 'etiqueta'],
          ['key' => 'usuarios',  'href' => '/usuarios',  'texto' => 'Usuarios',  'icono' => 'usuarios'],
      ]],
  ];
@endphp

{{-- Fondo oscuro detrás del menú en celular/tablet --}}
<div id="gs-sidebar-fondo" class="hidden fixed inset-0 z-30 bg-black/50 lg:hidden print:hidden"></div>

<aside id="gs-sidebar"
       class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 bg-negro text-crema flex flex-col
              -translate-x-full transition-transform duration-200
              lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 print:hidden">

  <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
    <a href="/dashboard" class="flex items-center gap-3 min-w-0">
      <img src="{{ asset('img/logo-sm.png') }}" alt="Restaurante Gold Street" class="h-12 w-12 object-contain shrink-0" />
      <span class="leading-tight">
        <span class="block font-display text-xl font-bold text-dorado">GoldTrack</span>
        <span class="block text-[10px] tracking-[0.22em] text-crema/60">GOLD STREET</span>
      </span>
    </a>
    <button id="gs-menu-cerrar" type="button" class="lg:hidden ml-auto p-2 rounded-md text-crema/70 hover:text-dorado" aria-label="Cerrar menú">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
    </button>
  </div>

  <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
    @foreach ($secciones as $seccion)
      <div @if($seccion['roles']) data-roles="{{ $seccion['roles'] }}" @endif>
        <p class="px-3 mb-2 text-[10px] uppercase tracking-[0.2em] text-crema/40">{{ $seccion['titulo'] }}</p>
        <div class="space-y-1">
          @foreach ($seccion['items'] as $item)
            <x-sidebar-link :href="$item['href']" :icono="$item['icono']" :roles="$item['roles'] ?? null"
                            :activo="$item['key'] === $active">
              {{ $item['texto'] }}
            </x-sidebar-link>
          @endforeach
        </div>
      </div>
    @endforeach
  </nav>

  <p class="px-5 py-4 text-[10px] tracking-[0.25em] text-dorado/70 border-t border-white/10">AMBIENTE · SABOR · ATENCIÓN</p>
</aside>
