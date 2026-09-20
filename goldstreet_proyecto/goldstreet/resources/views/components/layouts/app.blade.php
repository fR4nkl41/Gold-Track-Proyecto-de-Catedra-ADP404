{{--
  <x-layouts.app> : plantilla de las vistas autenticadas (sección 6).
  Compone los componentes independientes <x-sidebar>, <x-topbar> y <x-footer>;
  cada uno se puede apagar según la vista:

    <x-layouts.app title="Reportes" page="reportes" acceso="admin" :footer="false">

  Slots: el contenido principal va en el slot por defecto y los modales en <x-slot:modales>.
--}}
@props([
    'title' => null,
    'page' => null,
    'acceso' => null,
    'sidebar' => true,
    'topbar' => true,
    'footer' => true,
    'seccion' => null,   // opción del menú a resaltar si no coincide con page (ej. ficha de proveedor → catalogos)
])

<x-layouts.base :title="$title" :page="$page" :acceso="$acceso">
  <div class="min-h-screen lg:flex">
    @if ($sidebar)
      <x-sidebar :active="$seccion ?? $page" />
    @endif

    <div class="flex-1 min-w-0 flex flex-col min-h-screen">
      @if ($topbar)
        <x-topbar :menu="$sidebar" />
      @endif

      <main class="flex-1 w-full max-w-6xl mx-auto px-4 py-8">
        {{ $slot }}
      </main>

      @if ($footer)
        <x-footer />
      @endif
    </div>
  </div>

  {{ $modales ?? '' }}
  <x-confirmar />
</x-layouts.base>
